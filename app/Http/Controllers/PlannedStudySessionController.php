<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReschedulePlannedSessionRequest;
use App\Http\Requests\StorePlannedExecutionRequest;
use App\Http\Requests\StorePlannedStudySessionRequest;
use App\Http\Requests\WeeklyScheduleRequest;
use App\Models\PlannedStudySession;
use App\Services\PlannedExecutionService;
use App\Services\WeeklyScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlannedStudySessionController extends Controller
{
    public function index(WeeklyScheduleRequest $request, WeeklyScheduleService $schedule): View
    {
        return $this->scheduleView($request, $schedule);
    }

    public function edit(WeeklyScheduleRequest $request, PlannedStudySession $plannedSession, WeeklyScheduleService $schedule): View
    {
        Gate::authorize('update', $plannedSession);

        return $this->scheduleView($request, $schedule, $plannedSession);
    }

    public function store(StorePlannedStudySessionRequest $request, WeeklyScheduleService $schedule): RedirectResponse
    {
        $session = $schedule->save($request->user(), $request->validated());

        return redirect()->route('schedule.index', ['week' => $session->scheduled_date->toDateString()])->with('status', 'Sessao planejada criada.');
    }

    public function update(StorePlannedStudySessionRequest $request, PlannedStudySession $plannedSession, WeeklyScheduleService $schedule): RedirectResponse
    {
        $session = $schedule->save($request->user(), $request->validated(), $plannedSession);

        return redirect()->route('schedule.index', ['week' => $session->scheduled_date->toDateString()])->with('status', 'Sessao planejada atualizada.');
    }

    public function cancel(Request $request, PlannedStudySession $plannedSession, WeeklyScheduleService $schedule): RedirectResponse
    {
        Gate::authorize('cancel', $plannedSession);
        $validated = $request->validate(['revision' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $schedule->cancel($request->user(), $plannedSession, $validated['revision']);

        return redirect()->route('schedule.index', ['week' => $plannedSession->scheduled_date->toDateString()])->with('status', 'Sessao cancelada. Historico preservado.');
    }

    public function reschedule(ReschedulePlannedSessionRequest $request, PlannedStudySession $plannedSession, WeeklyScheduleService $schedule): RedirectResponse
    {
        $session = $schedule->reschedule($request->user(), $plannedSession, $request->validated());

        return redirect()->route('schedule.index', ['week' => $session->scheduled_date->toDateString()])->with('status', 'Sessao reagendada. Execucoes preservadas.');
    }

    public function execute(StorePlannedExecutionRequest $request, PlannedStudySession $plannedSession, PlannedExecutionService $executions): JsonResponse|RedirectResponse
    {
        $result = $executions->record($request->user(), $plannedSession, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['item_id' => $result['item']?->id, 'created' => $result['created']], $result['created'] ? 201 : 200);
        }

        return redirect()->route('schedule.index', ['week' => $plannedSession->scheduled_date->toDateString()])
            ->with('status', $result['created'] ? 'Execucao registrada com minutos reais.' : 'Envio ja processado. Nenhum estudo foi duplicado.');
    }

    private function scheduleView(WeeklyScheduleRequest $request, WeeklyScheduleService $schedule, ?PlannedStudySession $session = null): View
    {
        $subjects = $request->user()->subjects()
            ->where(fn ($query) => $query->where('active', true)->when($session !== null, fn ($query) => $query->orWhere('id', $session->subject_id)))
            ->orderBy('name')->get(['id', 'name', 'active']);

        return view('schedule.index', [
            'schedule' => $schedule->week($request->user(), $request->validated('week') ?? $session?->scheduled_date->toDateString()),
            'subjects' => $subjects,
            'editingSession' => $session,
            'initialDate' => $request->validated('date'),
        ]);
    }
}
