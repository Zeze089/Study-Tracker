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

class GoalFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'America/Fortaleza'));
    }

    /** @return array<string, Goal> */
    private function goalsFor(User $user): array
    {
        $base = ['user_id' => $user->id, 'type' => 'daily', 'metric' => 'days', 'target_days' => 1, 'active' => true];
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-12', 'studied' => true, 'minutes' => 30]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 90]);

        return [
            'ongoing' => Goal::factory()->create([...$base, 'type' => 'monthly', 'target_days' => 20, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']),
            'planned' => Goal::factory()->create([...$base, 'type' => 'weekly', 'target_days' => 4, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25']),
            'achieved' => Goal::factory()->create([...$base, 'metric' => 'minutes', 'target_days' => 0, 'target_minutes' => 60, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-13']),
            'closed' => Goal::factory()->create([...$base, 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-10']),
            'archived' => Goal::factory()->create([...$base, 'active' => false, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']),
            'past_success' => Goal::factory()->create([...$base, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']),
        ];
    }

    public function test_combined_filters_select_only_the_matching_historical_goal(): void
    {
        $user = User::factory()->create();
        $goals = $this->goalsFor($user);
        $this->goalsFor(User::factory()->create());
        $response = $this->actingAs($user)->get(route('goals.index', [
            'view' => 'history', 'status' => 'achieved', 'type' => 'daily', 'metric' => 'days',
            'from' => '2026-10-12', 'until' => '2026-10-12',
        ]));

        $response->assertOk()->assertSee('Historico de metas')->assertSee('Periodo encerrado com a meta atingida.');
        $page = $response->viewData('goals');
        $this->assertSame(1, $page->total());
        $this->assertSame($goals['past_success']->id, $page->items()[0]['goal']->id);
        $this->assertSame(['total' => 6, 'current' => 3, 'achieved' => 2, 'history' => 3], $response->viewData('summary'));
    }

    #[DataProvider('statuses')]
    public function test_each_status_filter_matches_the_displayed_state(string $status, array $expected): void
    {
        $user = User::factory()->create();
        $goals = $this->goalsFor($user);
        $response = $this->actingAs($user)->get(route('goals.index', ['status' => $status]))->assertOk();
        $ids = array_map(fn (array $item): int => $item['goal']->id, $response->viewData('goals')->items());
        $expectedIds = array_map(fn (string $key): int => $goals[$key]->id, $expected);
        sort($ids);
        sort($expectedIds);
        $this->assertSame($expectedIds, $ids);
    }

    /** @return array<string, array{string, array<string>}> */
    public static function statuses(): array
    {
        return [
            'planned' => ['planned', ['planned']],
            'ongoing' => ['ongoing', ['ongoing']],
            'achieved' => ['achieved', ['achieved', 'past_success']],
            'closed' => ['closed', ['closed']],
            'archived' => ['archived', ['archived']],
        ];
    }

    public function test_current_and_history_views_partition_the_users_goals(): void
    {
        $user = User::factory()->create();
        $this->goalsFor($user);
        $this->actingAs($user)->get(route('goals.index', ['view' => 'current']))->assertOk()->assertViewHas('goals', fn ($page): bool => $page->total() === 3);
        $this->get(route('goals.index', ['view' => 'history']))->assertOk()->assertViewHas('goals', fn ($page): bool => $page->total() === 3);
        $this->get(route('goals.index', ['from' => '2026-10-19', 'until' => '2026-10-19']))->assertOk()->assertViewHas('goals', fn ($page): bool => $page->total() === 2);
        $this->get(route('goals.index', ['until' => '2026-10-10']))->assertOk()->assertViewHas('goals', fn ($page): bool => $page->total() === 2);
    }

    public function test_pagination_preserves_combined_filters_and_keeps_summary_global(): void
    {
        $user = User::factory()->create();
        Goal::factory()->count(31)->create(['user_id' => $user->id, 'type' => 'daily', 'metric' => 'days', 'target_days' => 1, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-13', 'active' => true]);
        $filters = ['type' => 'daily', 'metric' => 'days', 'view' => 'current', 'per_page' => 15, 'page' => 2];
        $response = $this->actingAs($user)->get(route('goals.index', $filters))->assertOk();
        $page = $response->viewData('goals');
        $this->assertSame(31, $page->total());
        $this->assertCount(15, $page->items());
        $this->assertSame(2, $page->currentPage());
        parse_str(parse_url($page->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('daily', $query['type']);
        $this->assertSame('days', $query['metric']);
        $this->assertSame('current', $query['view']);
        $this->assertSame('3', $query['page']);
        $this->assertSame(31, $response->viewData('summary')['total']);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_validation_errors(array $filters, string $field): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('goals.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidFilters(): array
    {
        return [
            'view' => [['view' => 'secret'], 'view'],
            'type' => [['type' => 'yearly'], 'type'],
            'metric' => [['metric' => 'hours'], 'metric'],
            'status' => [['status' => 'invalid'], 'status'],
            'date' => [['from' => '2026-02-30'], 'from'],
            'reversed dates' => [['from' => '2026-10-20', 'until' => '2026-10-10'], 'until'],
            'page size' => [['per_page' => 10000], 'per_page'],
            'page' => [['page' => 0], 'page'],
        ];
    }

    public function test_empty_and_filtered_empty_states_are_distinct(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('goals.index'))->assertOk()->assertSee('Nenhuma meta cadastrada.')
            ->assertViewHas('summary', ['total' => 0, 'current' => 0, 'achieved' => 0, 'history' => 0]);
        $this->goalsFor($user);
        $this->get(route('goals.index', ['type' => 'weekly', 'metric' => 'minutes']))->assertOk()
            ->assertSee('Nenhuma meta corresponde aos filtros selecionados.');
    }

    public function test_indicators_and_filtered_pagination_use_three_queries_regardless_of_goal_count(): void
    {
        $user = User::factory()->create();
        $this->goalsFor($user);
        Goal::factory()->count(30)->create(['user_id' => $user->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service = app(GoalProgressService::class);
        $service->pageForUser($user, ['view' => 'history', 'metric' => 'days']);
        $summary = $service->summaryForUser($user);
        $this->assertSame(36, $summary['total']);
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_history_and_summary_follow_the_users_local_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 01:00:00', 'UTC'));
        $user = User::factory()->create(['timezone' => 'America/Fortaleza']);
        Goal::factory()->create(['user_id' => $user->id, 'type' => 'daily', 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-13', 'active' => true]);
        $service = app(GoalProgressService::class);
        $this->assertSame(1, $service->summaryForUser($user)['current']);
        $this->assertSame(0, $service->pageForUser($user, ['view' => 'history'])->total());
        $this->travel(4)->hours();
        $this->assertSame(1, $service->summaryForUser($user)['history']);
        $this->assertSame(1, $service->pageForUser($user, ['view' => 'history'])->total());
    }
}
