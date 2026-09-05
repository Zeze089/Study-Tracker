<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Subject::class);

        return view('subjects.index', [
            'subjects' => $request->user()
                ->subjects()
                ->withCount('studyRecords')
                ->orderByDesc('active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Subject::class);

        $request->user()->subjects()->create($this->payloadFromRequest($request, defaultActive: true));

        return back()->with('status', 'subject-saved');
    }

    public function update(StoreSubjectRequest $request, Subject $subject): RedirectResponse
    {
        Gate::authorize('update', $subject);

        $subject->update($this->payloadFromRequest($request, defaultActive: $subject->active));

        return back()->with('status', 'subject-saved');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        Gate::authorize('delete', $subject);

        if ($subject->studyRecords()->exists() || $subject->studyRecordItems()->exists()) {
            return back()->with('error', 'Esta materia possui registros vinculados. Desative-a para preservar o historico.');
        }

        $subject->delete();

        return back()->with('status', 'subject-deleted');
    }

    /**
     * @return array{name: string, color: string, active: bool}
     */
    private function payloadFromRequest(StoreSubjectRequest $request, bool $defaultActive): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'color' => $validated['color'],
            'active' => array_key_exists('active', $validated) ? $request->boolean('active') : $defaultActive,
        ];
    }
}
