<?php

namespace Tests\Feature\Services;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_february_with_the_correct_number_of_days(): void
    {
        $this->travelTo(Carbon::parse('2026-02-10 12:00:00'));
        $user = User::factory()->create();

        $calendar = app(CalendarService::class)->month($user, 2026, 2);
        $days = collect($calendar['weeks'])->flatten(1)->filter()->values();

        $this->assertCount(28, $days);
        $this->assertSame('2026-02-01', $days->first()['date']);
        $this->assertSame('2026-02-28', $days->last()['date']);
    }

    public function test_builds_leap_year_february_with_twenty_nine_days(): void
    {
        $this->travelTo(Carbon::parse('2028-02-10 12:00:00'));
        $user = User::factory()->create();

        $calendar = app(CalendarService::class)->month($user, 2028, 2);
        $days = collect($calendar['weeks'])->flatten(1)->filter()->values();

        $this->assertCount(29, $days);
        $this->assertSame('2028-02-29', $days->last()['date']);
    }

    public function test_december_navigation_points_to_january_of_the_next_year(): void
    {
        $this->travelTo(Carbon::parse('2026-12-10 12:00:00'));
        $user = User::factory()->create();

        $calendar = app(CalendarService::class)->month($user, 2026, 12);

        $this->assertSame(2027, $calendar['next_month']['year']);
        $this->assertSame(1, $calendar['next_month']['month']);
        $this->assertSame('Janeiro', $calendar['next_month']['label']);
    }

    public function test_january_navigation_points_to_december_of_the_previous_year(): void
    {
        $this->travelTo(Carbon::parse('2027-01-10 12:00:00'));
        $user = User::factory()->create();

        $calendar = app(CalendarService::class)->month($user, 2027, 1);

        $this->assertSame(2026, $calendar['previous_month']['year']);
        $this->assertSame(12, $calendar['previous_month']['month']);
        $this->assertSame('Dezembro', $calendar['previous_month']['label']);
    }

    public function test_month_records_are_loaded_with_a_small_query_count(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id]);

        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            StudyRecord::factory()->create([
                'user_id' => $user->id,
                'subject_id' => $subject->id,
                'study_date' => $date,
                'studied' => true,
                'minutes' => 60,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(CalendarService::class)->month($user, 2026, 9);

        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
    }
}
