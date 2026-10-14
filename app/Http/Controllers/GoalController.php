<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterGoalsRequest;
use App\Http\Requests\StoreGoalRequest;
use App\Models\Goal;
use App\Services\GoalProgressService;
use App\Services\GoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function index(FilterGoalsRequest $request, GoalProgressService $goals): View
    {
        Gate::authorize('viewAny', Goal::class);

        return view('goals.index', [
            'goals' => $goals->pageForUser($request->user(), $request->validated()),
            'filters' => $request->validated(),
            'summary' => $goals->summaryForUser($request->user()),
            'editingGoal' => null,
        ]);
    }

    public function edit(FilterGoalsRequest $request, Goal $goal, GoalProgressService $goals): View
    {
        Gate::authorize('update', $goal);

        return view('goals.index', [
            'goals' => $goals->pageForUser($request->user(), $request->validated()),
            'filters' => $request->validated(),
            'summary' => $goals->summaryForUser($request->user()),
            'editingGoal' => $goal,
        ]);
    }

    public function store(StoreGoalRequest $request, GoalService $goals): RedirectResponse
    {
        $goals->save($request->user(), $request->safe()->only(['type', 'metric', 'target_days', 'target_minutes', 'starts_on', 'ends_on']));

        return redirect()->route('goals.index')->with('status', 'Meta criada com sucesso.');
    }

    public function update(StoreGoalRequest $request, Goal $goal, GoalService $goals): RedirectResponse
    {
        $goals->save($request->user(), $request->safe()->only(['type', 'metric', 'target_days', 'target_minutes', 'starts_on', 'ends_on']), $goal);

        return redirect()->route('goals.index')->with('status', 'Meta atualizada com sucesso.');
    }

    public function archive(Request $request, Goal $goal, GoalService $goals): RedirectResponse
    {
        Gate::authorize('archive', $goal);
        $goals->archive($request->user(), $goal);

        return redirect()->route('goals.index')->with('status', 'Meta arquivada. O historico foi preservado.');
    }
}
