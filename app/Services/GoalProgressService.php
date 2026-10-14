<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\StudyRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GoalProgressService
{
    public function __construct(private StudyRecordFormatter $formatter) {}

    /** @return array<string, mixed> */
    public function dashboardData(User $user, ?CarbonInterface $today = null): array
    {
        $progress = $this->activeProgress($user, $today);
        $items = array_map(fn (array $item): array => [
            'id' => $item['goal']->id,
            'type' => $item['goal']->type,
            'metric' => $item['goal']->metric,
            'period_label' => $item['period_label'],
            'starts_on' => $item['goal']->starts_on->format('d/m/Y'),
            'ends_on' => $item['goal']->ends_on->format('d/m/Y'),
            'achieved_label' => $item['achieved_label'],
            'target_label' => $item['target_label'],
            'percentage' => $item['percentage'],
            'status' => $item['status'],
            'message' => $item['message'],
        ], $progress);
        $achieved = count(array_filter($items, fn (array $item): bool => $item['status'] === 'Atingida'));

        return ['total' => count($items), 'achieved' => $achieved, 'ongoing' => count($items) - $achieved, 'items' => $items];
    }

    /** @return array<int, array<string, mixed>> */
    public function activeProgress(User $user, ?CarbonInterface $today = null): array
    {
        $today = $this->dateForUser($user, $today);

        return $this->queryForUser($user, $today)
            ->where('active', true)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->orderBy('ends_on')
            ->get()
            ->map(fn (Goal $goal): array => $this->formatProgress($goal, $today))
            ->all();
    }

    /** @param array<string, mixed> $filters */
    public function pageForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $today = $this->dateForUser($user, null);

        $query = $this->categorizedQuery($user, $today);
        foreach (['type', 'metric'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['status'])) {
            $query->where('status_key', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('ends_on', '>=', $filters['from']);
        }
        if (! empty($filters['until'])) {
            $query->whereDate('starts_on', '<=', $filters['until']);
        }
        if (($filters['view'] ?? 'all') === 'current') {
            $query->where('active', true)->whereDate('ends_on', '>=', $today->toDateString());
        } elseif (($filters['view'] ?? 'all') === 'history') {
            $query->where(fn (Builder $query) => $query->where('active', false)->orWhereDate('ends_on', '<', $today->toDateString()));
        }

        return $query
            ->orderByDesc('active')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->appends(array_diff_key($filters, ['page' => true]))
            ->through(fn (Goal $goal): array => $this->formatProgress($goal, $today));
    }

    /** @return array<string, int> */
    public function summaryForUser(User $user): array
    {
        $today = $this->dateForUser($user, null)->toDateString();
        $summary = DB::query()->fromSub($this->categorizedQuery($user, $this->dateForUser($user, null)), 'summary')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN active = 1 AND DATE(ends_on) >= ? THEN 1 ELSE 0 END), 0) AS current', [$today])
            ->selectRaw("COALESCE(SUM(CASE WHEN status_key = 'achieved' THEN 1 ELSE 0 END), 0) AS achieved")
            ->selectRaw('COALESCE(SUM(CASE WHEN active = 0 OR DATE(ends_on) < ? THEN 1 ELSE 0 END), 0) AS history', [$today])
            ->first();

        return [
            'total' => (int) $summary->total,
            'current' => (int) $summary->current,
            'achieved' => (int) $summary->achieved,
            'history' => (int) $summary->history,
        ];
    }

    /** @return Builder<Goal> */
    private function categorizedQuery(User $user, CarbonImmutable $today): Builder
    {
        $classified = Goal::query()->fromSub($this->queryForUser($user, $today), 'goals')
            ->select('goals.*')
            ->selectRaw("CASE WHEN active = 0 THEN 'archived' WHEN DATE(starts_on) > ? THEN 'planned' WHEN (metric = 'minutes' AND target_minutes > 0 AND studied_minutes >= target_minutes) OR (metric = 'days' AND target_days > 0 AND studied_days >= target_days) THEN 'achieved' WHEN DATE(ends_on) < ? THEN 'closed' ELSE 'ongoing' END AS status_key", [$today->toDateString(), $today->toDateString()]);

        return Goal::query()->fromSub($classified, 'goals')->select('goals.*');
    }

    /** @return array<string, mixed> */
    public function progressForGoal(Goal $goal, ?CarbonInterface $today = null): array
    {
        $user = $goal->user;
        $today = $this->dateForUser($user, $today);
        $goal = $this->queryForUser($user, $today)->whereKey($goal->id)->firstOrFail();

        return $this->formatProgress($goal, $today);
    }

    /** @return Builder<Goal> */
    private function queryForUser(User $user, CarbonImmutable $today): Builder
    {
        return $user->goals()->getQuery()->select('goals.*')->addSelect([
            'studied_minutes' => StudyRecord::query()
                ->selectRaw('COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM study_record_items WHERE study_record_items.study_record_id = study_records.id) THEN (SELECT COALESCE(SUM(study_record_items.minutes), 0) FROM study_record_items WHERE study_record_items.study_record_id = study_records.id) ELSE COALESCE(study_records.minutes, 0) END), 0)')
                ->whereColumn('study_records.user_id', 'goals.user_id')
                ->where('studied', true)
                ->whereColumn('study_date', '>=', 'goals.starts_on')
                ->whereColumn('study_date', '<=', 'goals.ends_on')
                ->whereDate('study_date', '<=', $today->toDateString()),
            'studied_days' => StudyRecord::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('study_records.user_id', 'goals.user_id')
                ->where('studied', true)
                ->whereColumn('study_date', '>=', 'goals.starts_on')
                ->whereColumn('study_date', '<=', 'goals.ends_on')
                ->whereDate('study_date', '<=', $today->toDateString()),
        ]);
    }

    /** @return array<string, mixed> */
    private function formatProgress(Goal $goal, CarbonImmutable $today): array
    {
        $studiedDays = (int) $goal->getAttribute('studied_days');
        $studiedMinutes = (int) $goal->getAttribute('studied_minutes');
        $minutes = $goal->metric === Goal::METRIC_MINUTES;
        $target = $minutes ? (int) $goal->target_minutes : $goal->target_days;
        $achieved = $minutes ? $studiedMinutes : $studiedDays;
        $remaining = max(0, $target - $achieved);
        $date = $today->toDateString();
        $ended = $goal->ends_on->toDateString() < $date;
        $daysLeft = $ended ? 0 : (int) $today->diffInDays(CarbonImmutable::parse($goal->ends_on->toDateString(), $today->timezone)) + 1;
        $status = match (true) {
            ! $goal->active => 'Arquivada',
            $goal->starts_on->toDateString() > $date => 'Planejada',
            $achieved >= $target && $target > 0 => 'Atingida',
            $goal->ends_on->toDateString() < $date => 'Encerrada',
            default => 'Em andamento',
        };

        return [
            'goal' => $goal,
            'studied_days' => $studiedDays,
            'target_days' => $goal->target_days,
            'studied_minutes' => $studiedMinutes,
            'target_minutes' => $goal->target_minutes,
            'achieved' => $achieved,
            'target' => $target,
            'unit' => $minutes ? 'minutos' : 'dias',
            'achieved_label' => $minutes ? $this->formatter->durationLabelOrZero($achieved) : $achieved.' dias',
            'target_label' => $minutes ? $this->formatter->durationLabelOrZero($target) : $target.' dias',
            'remaining_label' => $minutes ? $this->formatter->durationLabelOrZero($remaining) : $remaining.' dias',
            'percentage' => $target > 0 ? min(100, (int) round($achieved / $target * 100)) : 0,
            'remaining_days' => max(0, $goal->target_days - $studiedDays),
            'status' => $status,
            'is_ended' => $ended,
            'days_left' => $daysLeft,
            'period_label' => match ($goal->type) {
                Goal::TYPE_DAILY => 'Meta diaria', Goal::TYPE_WEEKLY => 'Meta semanal', default => 'Meta mensal'
            },
            'duration_label' => $this->formatter->durationLabelOrZero($studiedMinutes),
            'message' => match (true) {
                ! $goal->active => 'Meta arquivada. Historico preservado.',
                $goal->starts_on->toDateString() > $date => 'O periodo desta meta ainda nao comecou.',
                $achieved >= $target && $target > 0 => $ended ? 'Periodo encerrado com a meta atingida.' : 'Meta atingida neste periodo.',
                $ended => 'Periodo encerrado sem atingir a meta.',
                default => $remaining.($minutes ? ' minutos restantes.' : ' dias de estudo restantes.'),
            },
        ];
    }

    private function dateForUser(User $user, ?CarbonInterface $date): CarbonImmutable
    {
        $timezone = $user->timezone ?: config('app.timezone');

        return ($date === null ? CarbonImmutable::now($timezone) : CarbonImmutable::instance($date)->setTimezone($timezone))->startOfDay();
    }
}
