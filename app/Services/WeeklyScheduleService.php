<?php

namespace App\Services;

use App\Models\PlannedStudySession;
use App\Models\StudyRecordItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WeeklyScheduleService
{
    public function __construct(private StudyRecordFormatter $formatter) {}

    /** @return array<string, mixed> */
    public function week(User $user, ?string $date = null): array
    {
        $timezone = $user->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = CarbonImmutable::parse($date ?? $today->toDateString(), $timezone)->startOfWeek(CarbonInterface::MONDAY);
        $end = $start->addDays(6);
        $sessions = $this->sessionsInPeriod($user, $start, $end, true);
        $days = [];
        $labels = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado', 'Domingo'];

        for ($index = 0; $index < 7; $index++) {
            $day = $start->addDays($index);
            $items = $sessions->filter(fn (PlannedStudySession $session): bool => $session->scheduled_date->toDateString() === $day->toDateString());
            $activeMinutes = (int) $items->whereNull('cancelled_at')->sum('planned_minutes');
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $labels[$index],
                'date_label' => $day->format('d/m'),
                'is_today' => $day->isSameDay($today),
                'duration_label' => $this->formatter->durationLabelOrZero($activeMinutes),
                'sessions' => $items->values(),
            ];
        }

        return [
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'label' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            'previous_week' => $start->subWeek()->toDateString(),
            'next_week' => $start->addWeek()->toDateString(),
            'current_week' => $today->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            'today' => $today->toDateString(),
            'timezone' => $timezone,
            'days' => $days,
            'active_count' => $sessions->whereNull('cancelled_at')->count(),
            'cancelled_count' => $sessions->whereNotNull('cancelled_at')->count(),
            'overdue_count' => $sessions->where('is_overdue', true)->count(),
            'planned_duration' => $this->formatter->durationLabelOrZero((int) $sessions->whereNull('cancelled_at')->sum('planned_minutes')),
        ];
    }

    /** @return Collection<int, PlannedStudySession> */
    public function sessionsInPeriod(User $user, ?CarbonImmutable $start, ?CarbonImmutable $end, bool $history = false): Collection
    {
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();
        $sessions = $user->plannedStudySessions()->with($history ? ['subject:id,user_id,name,color,active', 'changes'] : ['subject:id,user_id,name,color,active'])
            ->addSelect(['real_minutes' => StudyRecordItem::query()
                ->join('study_records', 'study_records.id', '=', 'study_record_items.study_record_id')
                ->selectRaw('COALESCE(SUM(study_record_items.minutes), 0)')
                ->whereColumn('planned_study_session_id', 'planned_study_sessions.id')
                ->where('study_records.user_id', $user->id)
                ->where('study_records.studied', true)
                ->whereDate('study_records.study_date', '<=', $today->toDateString())])
            ->when($start !== null, fn ($query) => $query->whereDate('scheduled_date', '>=', $start->toDateString()))
            ->when($end !== null, fn ($query) => $query->whereDate('scheduled_date', '<=', $end->toDateString()))
            ->when(! $history, fn ($query) => $query->whereNull('cancelled_at'))
            ->orderByRaw('scheduled_time IS NULL')
            ->orderBy('scheduled_time')->orderBy('id')->get();
        foreach ($sessions as $session) {
            $real = (int) $session->real_minutes;
            $session->setAttribute('execution_percentage', $session->planned_minutes > 0 ? min(100, (int) round($real / $session->planned_minutes * 100)) : 0);
            $session->setAttribute('real_duration', $this->formatter->durationLabelOrZero($real));
            $session->setAttribute('execution_status', match (true) {
                $session->cancelled_at !== null => 'Cancelada',
                $real >= $session->planned_minutes && $session->planned_minutes > 0 => 'Concluida',
                $real > 0 => 'Parcial',
                default => 'Planejada',
            });
            $now = CarbonImmutable::now($session->timezone);
            $deadline = CarbonImmutable::parse($session->scheduled_date->toDateString().' '.($session->scheduled_time ?: '23:59:59'), $session->timezone);
            $overdue = $session->cancelled_at === null && $real < $session->planned_minutes && $now->greaterThan($deadline);
            $session->setAttribute('is_overdue', $overdue);
            $session->setAttribute('delay_days', $overdue ? (int) $deadline->startOfDay()->diffInDays($now->startOfDay()) : 0);
            if ($history) {
                $session->setAttribute('revision_token', $session->revision());
            }
        }

        return $sessions;
    }

    /** @return array<string, mixed> */
    public function dashboardData(User $user): array
    {
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();
        $sessions = $this->sessionsInPeriod($user, null, $today->addDays(7));
        $pending = $sessions->filter(fn (PlannedStudySession $session): bool => $session->execution_status !== 'Concluida');
        $serialize = fn (PlannedStudySession $session): array => [
            'id' => $session->id, 'subject' => $session->subject->name, 'content' => $session->content,
            'date' => $session->scheduled_date->toDateString(), 'date_label' => $session->scheduled_date->format('d/m'),
            'time' => $session->scheduled_time ? substr($session->scheduled_time, 0, 5) : 'Sem horario',
            'status' => $session->execution_status, 'percentage' => $session->execution_percentage,
            'is_overdue' => $session->is_overdue,
        ];
        $weekStart = $today->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);
        $planned = (int) $sessions->filter(fn (PlannedStudySession $session): bool => $session->scheduled_date->toDateString() >= $weekStart->toDateString()
            && $session->scheduled_date->toDateString() <= $weekEnd->toDateString()
        )->sum('planned_minutes');

        return [
            'pending_count' => $pending->count(),
            'overdue_count' => $pending->where('is_overdue', true)->count(),
            'planned_minutes' => $planned,
            'planned_duration' => $this->formatter->durationLabelOrZero($planned),
            'upcoming' => $pending->where('is_overdue', false)->sortBy(fn ($session) => $session->scheduled_date->toDateString().' '.($session->scheduled_time ?: '99:99'))->take(5)->map($serialize)->values()->all(),
            'overdue' => $pending->where('is_overdue', true)->sortBy(fn ($session) => $session->scheduled_date->toDateString())->take(5)->map($serialize)->values()->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(User $user, array $data, ?PlannedStudySession $session = null): PlannedStudySession
    {
        return DB::transaction(function () use ($user, $data, $session): PlannedStudySession {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($session !== null) {
                $session = $user->plannedStudySessions()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('update', $session);
                $this->assertRevision($session, $data['revision'] ?? null);
            } else {
                Gate::forUser($user)->authorize('create', PlannedStudySession::class);
            }
            $subject = $user->subjects()->whereKey($data['subject_id'])->lockForUpdate()->first();
            if ($subject === null || (! $subject->active && $subject->id !== $session?->subject_id)) {
                throw ValidationException::withMessages(['subject_id' => 'A materia selecionada nao esta disponivel para este usuario.']);
            }
            $payload = [
                'subject_id' => $subject->id,
                'content' => $data['content'],
                'scheduled_date' => $data['scheduled_date'],
                'scheduled_time' => $data['scheduled_time'] ?? null,
                'priority' => $data['priority'],
                'planned_minutes' => $data['planned_minutes'],
                'timezone' => $session?->timezone ?? $user->timezone ?: config('app.timezone'),
            ];
            if ($session !== null) {
                $before = $session->planningSnapshot();
                $session->update($payload);
                $moved = $before['scheduled_date'] !== $session->scheduled_date->toDateString()
                    || $before['scheduled_time'] !== ($session->scheduled_time ? substr($session->scheduled_time, 0, 5) : null);
                $this->recordChange($user, $session, $moved ? 'rescheduled' : 'updated', $before, $data['change_reason'] ?? null);

                return $session;
            }

            $session = $user->plannedStudySessions()->create($payload);
            $this->recordChange($user, $session, 'created', []);

            return $session;
        }, 3);
    }

    public function cancel(User $user, PlannedStudySession $session, ?string $revision = null): void
    {
        DB::transaction(function () use ($user, $session, $revision): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = $user->plannedStudySessions()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('cancel', $session);
            $this->assertRevision($session, $revision);
            $before = $session->planningSnapshot();
            $session->forceFill(['cancelled_at' => now()])->save();
            $this->recordChange($user, $session, 'cancelled', $before);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function reschedule(User $user, PlannedStudySession $session, array $data): PlannedStudySession
    {
        return DB::transaction(function () use ($user, $session, $data): PlannedStudySession {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = $user->plannedStudySessions()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('reschedule', $session);
            $this->assertRevision($session, $data['revision']);
            $before = $session->planningSnapshot();
            $session->update(['scheduled_date' => $data['scheduled_date'], 'scheduled_time' => $data['scheduled_time'] ?? null]);
            $this->recordChange($user, $session, 'rescheduled', $before, $data['reason']);

            return $session;
        }, 3);
    }

    private function assertRevision(PlannedStudySession $session, ?string $revision): void
    {
        if ($revision !== null && ! hash_equals($session->revision(), $revision)) {
            throw ValidationException::withMessages(['revision' => 'A sessao foi alterada. Reabra a agenda antes de salvar.']);
        }
    }

    /** @param array<string, mixed> $before */
    private function recordChange(User $user, PlannedStudySession $session, string $action, array $before, ?string $reason = null): void
    {
        $after = $session->planningSnapshot();
        if ($before === $after) {
            return;
        }
        $session->changes()->create([
            'actor_id' => $user->id, 'actor_name' => $user->name,
            'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason,
        ]);
    }
}
