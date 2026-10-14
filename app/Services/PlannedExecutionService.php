<?php

namespace App\Services;

use App\Models\PlannedStudySession;
use App\Models\StudyRecordItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlannedExecutionService
{
    /** @param array<string, mixed> $data
     * @return array{item: StudyRecordItem|null, created: bool}
     */
    public function record(User $user, PlannedStudySession $session, array $data): array
    {
        return DB::transaction(function () use ($user, $session, $data): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = $user->plannedStudySessions()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('execute', $session);
            $key = strtolower($data['submission_key']);
            $content = filled($data['content'] ?? null) ? trim($data['content']) : null;
            $hash = hash('sha256', json_encode([$session->id, $data['study_date'], (int) $data['actual_minutes'], $content], JSON_THROW_ON_ERROR));
            $submission = DB::table('planned_execution_submissions')->where('user_id', $user->id)->where('submission_key', $key)->lockForUpdate()->first();
            if ($submission !== null) {
                if (! hash_equals($submission->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['submission_key' => 'Este envio ja foi utilizado com outros dados.']);
                }

                return ['item' => $submission->study_record_item_id === null ? null : StudyRecordItem::find($submission->study_record_item_id), 'created' => false];
            }
            $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->toDateString();
            if ($data['study_date'] > $today || (int) $data['actual_minutes'] < 1 || (int) $data['actual_minutes'] > 1440) {
                throw ValidationException::withMessages(['actual_minutes' => 'Data ou duracao real invalida.']);
            }
            $record = $user->studyRecords()->whereDate('study_date', $data['study_date'])->lockForUpdate()->first();
            if ($record === null) {
                $record = $user->studyRecords()->create(['study_date' => $data['study_date'], 'studied' => true]);
            }
            $items = $record->items()->lockForUpdate()->get();
            // Preserve historical totals that have no item representation yet.
            if ($items->isEmpty() && $record->studied && ($record->minutes !== null || $record->subject_id !== null || $record->content !== null)) {
                $items->push($record->items()->create([
                    'subject_id' => $record->subject_id, 'content' => $record->content,
                    'minutes' => $record->minutes, 'position' => 1,
                ]));
            }
            if ($items->count() >= 12) {
                throw ValidationException::withMessages(['actual_minutes' => 'O dia ja possui o limite de 12 itens de estudo.']);
            }
            $total = (int) $items->sum('minutes') + (int) $data['actual_minutes'];
            if ($total > 1440) {
                throw ValidationException::withMessages(['actual_minutes' => 'O total real do dia nao pode ultrapassar 24 horas.']);
            }
            $item = new StudyRecordItem([
                'subject_id' => $session->subject_id, 'content' => $content ?? $session->content,
                'minutes' => (int) $data['actual_minutes'], 'position' => (int) $items->max('position') + 1,
            ]);
            $item->forceFill(['planned_study_session_id' => $session->id]);
            $record->items()->save($item);
            $first = $items->first() ?? $item;
            $record->update(['studied' => true, 'minutes' => $total, 'subject_id' => $first->subject_id, 'content' => $first->content]);
            DB::table('planned_execution_submissions')->insert([
                'user_id' => $user->id, 'planned_study_session_id' => $session->id,
                'study_record_item_id' => $item->id, 'submission_key' => $key, 'payload_hash' => $hash,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['item' => $item, 'created' => true];
        }, 3);
    }
}
