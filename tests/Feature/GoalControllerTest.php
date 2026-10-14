<?php

namespace Tests\Feature;

use App\Http\Requests\StoreGoalRequest;
use App\Models\Goal;
use App\Models\User;
use App\Services\GoalService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'America/Fortaleza'));
    }

    /** @return array<string, int|string> */
    private function payload(): array
    {
        return ['type' => 'weekly', 'target_days' => 4, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18'];
    }

    public function test_guests_cannot_access_goal_management(): void
    {
        $this->get(route('goals.index'))->assertRedirect(route('login'));
        $this->post(route('goals.store'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_user_can_create_weekly_and_monthly_goals_without_assigning_another_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), [...$this->payload(), 'user_id' => $other->id, 'active' => false])
            ->assertRedirect(route('goals.index'))->assertSessionHasNoErrors();
        $this->post(route('goals.store'), ['type' => 'monthly', 'target_days' => 20, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('goals', ['user_id' => $user->id, 'type' => 'weekly', 'active' => true]);
        $this->assertDatabaseHas('goals', ['user_id' => $user->id, 'type' => 'monthly']);
        $this->assertSame(0, $other->goals()->count());
    }

    #[DataProvider('invalidGoals')]
    public function test_invalid_goal_rules_are_rejected(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), array_replace($this->payload(), $changes))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('goals', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidGoals(): array
    {
        return [
            'unknown period' => [['type' => 'yearly'], 'type'],
            'zero target' => [['target_days' => 0], 'target_days'],
            'fraction target' => [['target_days' => 2.5], 'target_days'],
            'weekly capacity' => [['target_days' => 8], 'target_days'],
            'wrong weekly start' => [['starts_on' => '2026-10-13'], 'ends_on'],
            'wrong weekly end' => [['ends_on' => '2026-10-19'], 'ends_on'],
            'reversed dates' => [['ends_on' => '2026-10-11'], 'ends_on'],
            'invalid date' => [['starts_on' => '2026-02-30'], 'starts_on'],
            'ended period' => [['starts_on' => '2026-10-05', 'ends_on' => '2026-10-11'], 'ends_on'],
            'incomplete month' => [['type' => 'monthly', 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-31'], 'ends_on'],
            'february capacity' => [['type' => 'monthly', 'starts_on' => '2027-02-01', 'ends_on' => '2027-02-28', 'target_days' => 29], 'target_days'],
        ];
    }

    public function test_leap_month_and_week_crossing_a_year_are_valid(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), ['type' => 'monthly', 'target_days' => 29, 'starts_on' => '2028-02-01', 'ends_on' => '2028-02-29'])
            ->assertSessionHasNoErrors();
        $this->post(route('goals.store'), ['type' => 'weekly', 'target_days' => 7, 'starts_on' => '2026-12-28', 'ends_on' => '2027-01-03'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('goals', 2);
    }

    public function test_overlap_is_rejected_but_archived_goals_do_not_block_replacement(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, ...$this->payload(), 'active' => true]);
        $this->actingAs($user)->post(route('goals.store'), $this->payload())->assertSessionHasErrors('starts_on');
        $this->patch(route('goals.archive', $goal))->assertRedirect(route('goals.index'));
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'active' => false]);
        $this->post(route('goals.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('goals', 2);
    }

    public function test_user_can_edit_own_open_goal_but_cannot_edit_closed_or_archived_goals(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, ...$this->payload(), 'active' => true]);
        $this->actingAs($user)->get(route('goals.edit', $goal))->assertOk()->assertSee('Editar meta');
        $this->put(route('goals.update', $goal), [...$this->payload(), 'target_days' => 6])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'target_days' => 6]);

        $goal->update(['ends_on' => '2026-10-11']);
        $this->put(route('goals.update', $goal), $this->payload())->assertForbidden();
        $this->patch(route('goals.archive', $goal))->assertRedirect(route('goals.index'));
        $this->get(route('goals.edit', $goal))->assertForbidden();
        $this->put(route('goals.update', $goal), $this->payload())->assertForbidden();
    }

    public function test_other_users_goals_are_hidden_and_cannot_be_modified(): void
    {
        $owner = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $owner->id, ...$this->payload(), 'active' => true]);
        $this->actingAs(User::factory()->create())->get(route('goals.index'))
            ->assertOk()->assertSee('Nenhuma meta cadastrada.')->assertDontSee('Progresso da meta '.$goal->id);
        $this->get(route('goals.edit', $goal))->assertForbidden();
        $this->put(route('goals.update', $goal), $this->payload())->assertForbidden();
        $this->patch(route('goals.archive', $goal))->assertForbidden();
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'user_id' => $owner->id, 'active' => true]);
    }

    public function test_update_cannot_overlap_another_active_goal(): void
    {
        $user = User::factory()->create();
        Goal::factory()->create(['user_id' => $user->id, ...$this->payload(), 'active' => true]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'type' => 'weekly', 'target_days' => 3, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'active' => true]);

        $this->actingAs($user)->put(route('goals.update', $goal), $this->payload())->assertSessionHasErrors('starts_on');
        $this->assertSame('2026-10-19', $goal->fresh()->starts_on->toDateString());
    }

    public function test_two_prevalidated_requests_are_rechecked_at_persistence(): void
    {
        $user = User::factory()->create();
        $requests = [];
        for ($index = 0; $index < 2; $index++) {
            $request = StoreGoalRequest::create('/metas', 'POST', $this->payload());
            $request->setContainer($this->app)->setRedirector($this->app['redirect']);
            $request->setUserResolver(fn () => $user);
            $request->validateResolved();
            $requests[] = $request;
        }

        $service = app(GoalService::class);
        $service->save($user, $requests[0]->validated());
        try {
            $service->save($user, $requests[1]->validated());
            $this->fail('The second request must reject the overlapping goal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('starts_on', $exception->errors());
        }
        $this->assertSame(1, $user->goals()->where('active', true)->count());
    }

    public function test_stale_edit_cannot_reactivate_an_archived_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, ...$this->payload(), 'active' => true]);
        $stale = $goal->replicate();
        $stale->id = $goal->id;
        app(GoalService::class)->archive($user, $goal);

        $this->expectException(AuthorizationException::class);
        app(GoalService::class)->save($user, $this->payload(), $stale);
    }
}
