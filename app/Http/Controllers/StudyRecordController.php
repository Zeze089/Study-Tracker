<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudyRecordRequest;
use App\Models\StudyRecord;
use App\Services\StudyRecordFormatter;
use App\Services\StudyRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StudyRecordController extends Controller
{
    public function __construct(private StudyRecordFormatter $studyRecordFormatter) {}

    public function store(StoreStudyRecordRequest $request, StudyRecordService $studyRecords): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', StudyRecord::class);

        $studyRecord = $studyRecords->upsertForUser($request->user(), $request);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Registro salvo com sucesso.',
                'record' => $this->studyRecordFormatter->serialize($studyRecord),
            ], $studyRecord->wasRecentlyCreated ? 201 : 200);
        }

        return back()->with('status', 'study-record-saved');
    }

    public function update(
        StoreStudyRecordRequest $request,
        StudyRecord $studyRecord,
        StudyRecordService $studyRecords
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $studyRecord);

        $studyRecord = $studyRecords->update($studyRecord, $request);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Registro atualizado com sucesso.',
                'record' => $this->studyRecordFormatter->serialize($studyRecord),
            ]);
        }

        return back()->with('status', 'study-record-saved');
    }
}
