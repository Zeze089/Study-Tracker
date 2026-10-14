<?php

namespace App\Http\Controllers;

use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request, CalendarService $calendar): View|JsonResponse
    {
        $user = $request->user();
        $date = now($user->timezone ?: config('app.timezone'));
        [$year, $month] = $calendar->resolvePeriod(
            $user,
            $request->query('year', $date->year),
            $request->query('month', $date->month)
        );
        if ($request->expectsJson()) {
            return response()->json(['planning' => $calendar->planningMarkers($user, $year, $month)]);
        }
        $todayRecord = $user->studyRecords()
            ->with('subject:id,name,color')
            ->whereDate('study_date', $date->toDateString())
            ->first();

        return view('calendar.index', [
            'calendar' => $calendar->month($user, $year, $month),
            'today' => $date->toImmutable()->startOfDay(),
            'todayRecord' => $todayRecord,
            'subjects' => $user->subjects()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }
}
