<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\StudyRecord;
use App\Models\User;
use App\Services\GoalProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoalTimeTest extends TestCase
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
        return ['type' => 'daily', 'metric' => 'minutes', 'target_minutes' => 90, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-13'];
    }

    public function test_daily_days_and_time_goals_can_coexist_but_duplicates_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('goals.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('goals.store'), [...$this->payload(), 'metric' => 'days', 'target_days' => 1])->assertSessionHasNoErrors();
        $this->post(route('goals.store'), $this->payload())->assertSessionHasErrors('starts_on');
        $this->assertDatabaseCount('goals', 2);
        $this->assertDatabaseHas('goals', ['user_id' => $user->id, 'metric' => 'minutes', 'target_days' => 0, 'target_minutes' => 90]);
        $this->assertDatabaseHas('goals', ['user_id' => $user->id, 'metric' => 'days', 'target_days' => 1, 'target_minutes' => null]);
        $this->get(route('goals.index'))->assertOk()->assertSee('Meta diaria')->assertSee('Minutos-alvo')->assertSee('1h 30min');
    }

    #[DataProvider('invalidGoals')]
    public function test_time_and_daily_rules_are_enforced(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->create())->post(route('goals.store'), array_replace($this->payload(), $changes))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('goals', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidGoals(): array
    {
        return [
            'unknown metric' => [['metric' => 'hours'], 'metric'],
            'missing minutes' => [['target_minutes' => null], 'target_minutes'],
            'zero minutes' => [['target_minutes' => 0], 'target_minutes'],
            'fraction minutes' => [['target_minutes' => 1.5], 'target_minutes'],
            'daily capacity' => [['target_minutes' => 1441], 'target_minutes'],
            'weekly capacity' => [['type' => 'weekly', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'target_minutes' => 10081], 'target_minutes'],
            'february capacity' => [['type' => 'monthly', 'starts_on' => '2027-02-01', 'ends_on' => '2027-02-28', 'target_minutes' => 40321], 'target_minutes'],
            'daily interval' => [['ends_on' => '2026-10-14'], 'ends_on'],
            'daily days capacity' => [['metric' => 'days', 'target_days' => 2], 'target_days'],
        ];
    }

    public function test_time_progress_uses_items_or_legacy_minutes_without_double_counting(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, 'type' => 'weekly', 'metric' => 'minutes', 'target_days' => 0, 'target_minutes' => 120, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18']);
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-12', 'studied' => true, 'minutes' => 999]);
        $record->items()->delete();
        $record->items()->createMany([
            ['minutes' => 30, 'position' => 1],
            ['minutes' => 45, 'position' => 2],
            ['minutes' => null, 'position' => 3],
        ]);
        $legacy = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 45]);
        $legacy->items()->delete();
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-14', 'studied' => true, 'minutes' => 500]);
        StudyRecord::factory()->create(['user_id' => User::factory()->create()->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 500]);

        $service = app(GoalProgressService::class);
        $progress = $service->progressForGoal($goal);
        $this->assertSame(120, $progress['studied_minutes']);
        $this->assertSame(100, $progress['percentage']);
        $this->assertSame('Atingida', $progress['status']);
        $this->assertSame('2h', $progress['achieved_label']);

        $record->items()->where('position', 2)->update(['minutes' => 15]);
        $progress = $service->progressForGoal($goal);
        $this->assertSame(90, $progress['studied_minutes']);
        $this->assertSame(75, $progress['percentage']);
        $this->assertSame('30min', $progress['remaining_label']);

        $legacy->update(['studied' => false]);
        $this->assertSame(45, $service->progressForGoal($goal)['studied_minutes']);
    }

    public function test_null_item_durations_do_not_fall_back_to_the_daily_total(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, ...$this->payload(), 'target_days' => 0]);
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 90]);
        $record->items()->update(['minutes' => null]);
        $progress = app(GoalProgressService::class)->progressForGoal($goal);
        $this->assertSame(0, $progress['studied_minutes']);
        $this->assertSame(1, $progress['studied_days']);
    }

    public function test_daily_validation_and_policy_follow_local_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-13 01:00:00', 'UTC'));
        $user = User::factory()->create(['timezone' => 'America/Fortaleza']);
        $this->actingAs($user)->post(route('goals.store'), [...$this->payload(), 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12'])->assertSessionHasNoErrors();
        $goal = $user->goals()->firstOrFail();
        $this->get(route('goals.edit', $goal))->assertOk();
        $this->travel(4)->hours();
        $this->get(route('goals.edit', $goal))->assertForbidden();
        $this->post(route('goals.store'), [...$this->payload(), 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12'])->assertSessionHasErrors('ends_on');
    }

    public function test_minutes_goals_are_protected_and_metric_changes_clear_the_old_target(): void
    {
        $owner = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $owner->id, ...$this->payload(), 'target_days' => 0, 'active' => true]);
        $this->actingAs(User::factory()->create())->put(route('goals.update', $goal), $this->payload())->assertForbidden();
        $this->patch(route('goals.archive', $goal))->assertForbidden();
        $this->actingAs($owner)->put(route('goals.update', $goal), [...$this->payload(), 'metric' => 'days', 'target_days' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'metric' => 'days', 'target_minutes' => null, 'target_days' => 1]);
        $this->put(route('goals.update', $goal), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'metric' => 'minutes', 'target_days' => 0, 'target_minutes' => 90]);
    }

    public function test_switching_metric_cannot_duplicate_an_existing_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('goals.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('goals.store'), [...$this->payload(), 'metric' => 'days', 'target_days' => 1])->assertSessionHasNoErrors();
        $days = $user->goals()->where('metric', 'days')->firstOrFail();
        $this->put(route('goals.update', $days), $this->payload())->assertSessionHasErrors('starts_on');
        $this->assertSame('days', $days->fresh()->metric);
        $minutes = $user->goals()->where('metric', 'minutes')->firstOrFail();
        $this->patch(route('goals.archive', $minutes))->assertRedirect(route('goals.index'));
        $this->put(route('goals.update', $days), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('minutes', $days->fresh()->metric);
    }

    public function test_leap_month_time_capacity_and_excess_progress_are_supported(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('goals.store'), [
            'type' => 'monthly', 'metric' => 'minutes', 'target_minutes' => 41760,
            'starts_on' => '2028-02-01', 'ends_on' => '2028-02-29',
        ])->assertSessionHasNoErrors();
        $this->post(route('goals.store'), $this->payload())->assertSessionHasNoErrors();
        $goal = $user->goals()->where('type', 'daily')->firstOrFail();
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 180]);
        $progress = app(GoalProgressService::class)->progressForGoal($goal);
        $this->assertSame(180, $progress['achieved']);
        $this->assertSame(100, $progress['percentage']);
        $this->assertSame('0min', $progress['remaining_label']);
    }

    public function test_additive_migration_preserves_legacy_goals_and_study_data(): void
    {
        $user = User::factory()->create();
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true]);
        $migration = require database_path('migrations/2026_10_13_163632_add_time_metrics_to_goals_table.php');
        $migration->down();
        $id = DB::table('goals')->insertGetId(['user_id' => $user->id, 'type' => 'weekly', 'target_days' => 4, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'active' => true]);
        $migration->up();

        $goal = Goal::findOrFail($id);
        $this->assertSame('days', $goal->metric);
        $this->assertSame(4, $goal->target_days);
        $this->assertNull($goal->target_minutes);
        $this->assertSame($user->id, $goal->user_id);
        $this->assertDatabaseHas('study_records', ['id' => $record->id, 'user_id' => $user->id]);
        $progress = app(GoalProgressService::class)->progressForGoal($goal);
        $this->assertSame(1, $progress['studied_days']);
        $this->actingAs($user)->put(route('goals.update', $goal), ['type' => 'weekly', 'target_days' => 5, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18'])->assertSessionHasNoErrors();
        $this->assertSame('days', $goal->fresh()->metric);
    }
}
