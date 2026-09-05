<?php

namespace App\Http\Controllers;

use App\Services\StreakService;
use App\Services\StudyStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthlyReportController extends Controller
{
    public function __invoke(Request $request, StudyStatsService $stats, StreakService $streaks): View
    {
        $user = $request->user();

        return view('reports.monthly', [
            'monthSummary' => $stats->monthSummary($user),
            'subjectSummary' => $stats->subjectSummary($user),
            'currentStreak' => $streaks->currentStreak($user),
            'longestStreak' => $streaks->longestStreak($user),
        ]);
    }
}
