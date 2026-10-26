<?php

namespace App\Services;

use App\PmsJob;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The PMS reports - read-only queries over pms_jobs and friends. Each
 * report has one query (filters + sort) and one row mapper, used both for
 * the page and for its Excel export, so the two can't disagree.
 *
 * Queries are plain query builders with joins rather than Eloquent with
 * eager loads, and every date comparison is written portably (no
 * DATEDIFF etc.) - "days overdue" is ordered by due_date and computed in
 * PHP for the rows actually shown.
 */
class PmsReports
{
    /** Column key => heading, in display/export order. */
    const OVERVIEW_COLUMNS = [
        'vessel' => 'Vessel', 'open' => 'Open jobs', 'planned' => 'Planned', 'due_soon' => 'Due Soon',
        'overdue' => 'Overdue', 'approval' => 'Waiting Approval', 'critical_overdue' => 'Critical overdue',
        'pct_overdue' => '% Overdue', 'last_completed' => 'Last job completed', 'stale_counters' => 'Counters not updated > 7 days',
    ];

    const OVERDUE_COLUMNS = [
        'vessel' => 'Vessel', 'location' => 'Location', 'path' => 'Component path', 'job' => 'Job', 'type' => 'Type',
        'rank' => 'Rank', 'due_date' => 'Due date', 'due_rh' => 'Due RH', 'current_rh' => 'Current RH',
        'days' => 'Days overdue', 'critical' => 'Critical', 'reschedule_reason' => 'Reschedule reason',
    ];

    const HISTORY_COLUMNS = [
        'completed_at' => 'Completed on', 'vessel' => 'Vessel', 'path' => 'Component path', 'job' => 'Job', 'type' => 'Type',
        'done_by' => 'Done by (rank)', 'rh_at_completion' => 'RH at completion', 'due_date' => 'Due date', 'days_late' => 'Days late',
        'condition' => 'Condition after', 'overdue_reason' => 'Overdue reason', 'remark' => 'Remark', 'approved' => 'Approved by / at',
    ];

    /** Sortable columns => SQL they order by. "days" is due_date reversed: most overdue = earliest due date. */
    const OVERDUE_SORTS = [
        'vessel' => 'v.name', 'location' => 'l.name', 'path' => 'c.name', 'job' => 'd.name', 'type' => 't.name',
        'rank' => 'r.code', 'due_date' => 'j.due_date', 'due_rh' => 'j.due_running_hours', 'days' => 'days', 'critical' => 'critical',
    ];

    const HISTORY_SORTS = [
        'completed_at' => 'j.completed_at', 'vessel' => 'v.name', 'path' => 'c.name', 'job' => 'd.name', 'type' => 't.name',
        'done_by' => 'dr.code', 'rh_at_completion' => 'j.rh_at_completion', 'due_date' => 'j.due_date', 'condition' => 'ca.name',
    ];

    private array $componentsByVessel = [];

    // ------------------------------------------------------------------
    // 1. Vessel overview
    // ------------------------------------------------------------------

    /**
     * One row per vessel, plus fleet totals.
     *
     * @param  Collection  $vessels  id + name of the vessels in scope
     * @return array{rows: Collection, totals: array}
     */
    public function overview(Collection $vessels): array
    {
        $ids = $vessels->pluck('id')->all();
        $today = CarbonImmutable::today();

        $byStatus = DB::table('pms_jobs')->whereIn('vessel_id', $ids)->whereIn('status', PmsJob::OPEN_STATUSES)
            ->groupBy('vessel_id', 'status')->select('vessel_id', 'status', DB::raw('count(*) as n'))->get()
            ->groupBy('vessel_id');

        $criticalOverdue = DB::table('pms_jobs as j')
            ->join('pms_job_definitions as d', 'd.id', '=', 'j.job_definition_id')
            ->join('pms_components as c', 'c.id', '=', 'j.component_id')
            ->whereIn('j.vessel_id', $ids)->where('j.status', PmsJob::OVERDUE)
            ->where(fn ($q) => $q->where('d.is_critical', true)->orWhere('c.is_critical', true))
            ->groupBy('j.vessel_id')->pluck(DB::raw('count(*)'), 'j.vessel_id');

        $lastCompleted = DB::table('pms_jobs')->whereIn('vessel_id', $ids)
            ->whereIn('status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL])->whereNotNull('completed_at')
            ->groupBy('vessel_id')->pluck(DB::raw('max(completed_at)'), 'vessel_id');

        // A counter is stale when it has no reading in the last 7 days. Own
        // meters only - a dependent counter never has readings of its own
        // (they're always recorded against its counter_parent_id instead),
        // so it would always show as stale otherwise.
        $staleCounters = DB::table('pms_components as c')
            ->whereIn('c.vessel_id', $ids)->where('c.has_counter', true)->whereNull('c.counter_parent_id')
            ->where('c.is_inactive', false)->whereNull('c.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('pms_counter_readings as r')
                ->whereColumn('r.component_id', 'c.id')->where('r.reading_date', '>=', $today->subDays(7)->toDateString()))
            ->groupBy('c.vessel_id')->pluck(DB::raw('count(*)'), 'c.vessel_id');

        $rows = $vessels->map(function ($vessel) use ($byStatus, $criticalOverdue, $lastCompleted, $staleCounters) {
            $counts = collect($byStatus->get($vessel->id, []))->pluck('n', 'status');
            $row = [
                'vessel_id' => $vessel->id,
                'vessel' => $vessel->name,
                'planned' => (int) ($counts[PmsJob::PLANNED] ?? 0),
                'due_soon' => (int) ($counts[PmsJob::DUE_SOON] ?? 0),
                'overdue' => (int) ($counts[PmsJob::OVERDUE] ?? 0),
                'approval' => (int) ($counts[PmsJob::WAITING_APPROVAL] ?? 0),
                'critical_overdue' => (int) ($criticalOverdue[$vessel->id] ?? 0),
                'last_completed' => $this->dateOnly($lastCompleted[$vessel->id] ?? null),
                'stale_counters' => (int) ($staleCounters[$vessel->id] ?? 0),
            ];
            $row['open'] = $row['planned'] + $row['due_soon'] + $row['overdue'] + $row['approval'];
            $row['pct_overdue'] = $this->percent($row['overdue'], $row['open']);

            return $row;
        });

        $totals = ['vessel' => 'Fleet total'];
        foreach (['open', 'planned', 'due_soon', 'overdue', 'approval', 'critical_overdue', 'stale_counters'] as $key) {
            $totals[$key] = $rows->sum($key);
        }
        $totals['pct_overdue'] = $this->percent($totals['overdue'], $totals['open']);
        $totals['last_completed'] = $rows->pluck('last_completed')->filter()->max();

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** green < 5%, amber 5-15%, red > 15%. */
    public static function overdueBand(?float $pct): string
    {
        if ($pct === null) {
            return '';
        }

        return $pct < 5 ? 'good' : ($pct <= 15 ? 'warn' : 'bad');
    }

    // ------------------------------------------------------------------
    // 2. Overdue jobs
    // ------------------------------------------------------------------

    /**
     * @param  array{vessel_ids:?array, location_id:?int, rank_id:?int, job_type_id:?int, critical:bool, days:?string}  $f
     */
    public function overdueQuery(array $f, ?string $sort = null, string $dir = 'desc'): Builder
    {
        $today = CarbonImmutable::today();

        $q = DB::table('pms_jobs as j')
            ->join('pms_job_definitions as d', 'd.id', '=', 'j.job_definition_id')
            ->join('pms_components as c', 'c.id', '=', 'j.component_id')
            ->join('vessels as v', 'v.id', '=', 'j.vessel_id')
            ->leftJoin('pms_locations as l', 'l.id', '=', 'c.location_id')
            ->leftJoin('ranks as r', 'r.id', '=', 'd.rank_id')
            ->leftJoin('pms_job_types as t', 't.id', '=', 'd.job_type_id')
            ->where('j.status', PmsJob::OVERDUE)
            ->when($f['vessel_ids'] !== null, fn ($q) => $q->whereIn('j.vessel_id', $f['vessel_ids']))
            ->when($f['location_id'], fn ($q, $id) => $q->where('c.location_id', $id))
            ->when($f['rank_id'], fn ($q, $id) => $q->where('d.rank_id', $id))
            ->when($f['job_type_id'], fn ($q, $id) => $q->where('d.job_type_id', $id))
            ->when($f['critical'], fn ($q) => $q->where(fn ($q) => $q->where('d.is_critical', true)->orWhere('c.is_critical', true)))
            // "> N days overdue" = due before (today - N).
            ->when($f['days'] === '30', fn ($q) => $q->where('j.due_date', '<', $today->subDays(30)->toDateString()))
            ->when($f['days'] === '90', fn ($q) => $q->where('j.due_date', '<', $today->subDays(90)->toDateString()))
            ->select([
                'j.id', 'j.vessel_id', 'j.component_id', 'j.due_date', 'j.due_running_hours', 'j.reschedule_reason',
                'v.name as vessel', 'l.name as location', 'd.name as job', 't.name as type', 'r.code as rank_code', 'r.name as rank_name',
                DB::raw('case when d.is_critical = 1 or c.is_critical = 1 then 1 else 0 end as critical'),
            ]);

        $sort = isset(self::OVERDUE_SORTS[$sort]) ? $sort : 'days';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        if ($sort === 'days') {
            // Highest days overdue = earliest due date; jobs overdue only on
            // running hours (no due date) go last either way.
            $q->orderByRaw('j.due_date is null')->orderBy('j.due_date', $dir === 'desc' ? 'asc' : 'desc');
        } elseif ($sort === 'critical') {
            $q->orderBy(DB::raw('case when d.is_critical = 1 or c.is_critical = 1 then 1 else 0 end'), $dir);
        } else {
            $q->orderBy(self::OVERDUE_SORTS[$sort], $dir);
        }

        return $q->orderBy('j.id');
    }

    /** Totals for the summary strip: overall, by rank, by days-overdue band. */
    public function overdueSummary(array $f): array
    {
        $today = CarbonImmutable::today();
        $base = fn () => $this->overdueQuery($f)->reorder();

        $byRank = (clone $base())->select('r.code as rank', DB::raw('count(*) as n'))
            ->groupBy('r.code')->orderByDesc('n')->get()
            ->map(fn ($row) => ['rank' => $row->rank ?: '—', 'count' => (int) $row->n]);

        $d30 = $today->subDays(30)->toDateString();
        $d90 = $today->subDays(90)->toDateString();

        return [
            'total' => $base()->count(),
            'by_rank' => $byRank,
            'bands' => [
                '0–30 days' => $base()->where('j.due_date', '>=', $d30)->count(),
                '31–90 days' => $base()->where('j.due_date', '<', $d30)->where('j.due_date', '>=', $d90)->count(),
                '90+ days' => $base()->where('j.due_date', '<', $d90)->count(),
                'Running hours only' => $base()->whereNull('j.due_date')->count(),
            ],
        ];
    }

    /** Overdue query rows => display rows (keys of OVERDUE_COLUMNS, plus ids for links). */
    public function overdueRows(iterable $records): array
    {
        $records = collect($records);
        $today = CarbonImmutable::today();
        $rh = $this->currentRh($records->pluck('component_id')->unique()->all());

        return $records->map(function ($r) use ($today, $rh) {
            $due = $r->due_date ? CarbonImmutable::parse($r->due_date)->startOfDay() : null;

            return [
                'job_id' => $r->id,
                'vessel_id' => $r->vessel_id,
                'vessel' => $r->vessel,
                'location' => $r->location,
                'path' => $this->path($r->vessel_id, $r->component_id, false),
                'job' => $r->job,
                'type' => $r->type,
                'rank' => $r->rank_code,
                'due_date' => $due?->toDateString(),
                'due_rh' => $r->due_running_hours !== null ? (float) $r->due_running_hours : null,
                'current_rh' => $rh[$r->component_id] ?? null,
                'days' => $due ? max(0, (int) $due->diffInDays($today, false)) : null,
                'critical' => (bool) $r->critical,
                'reschedule_reason' => $r->reschedule_reason,
            ];
        })->all();
    }

    // ------------------------------------------------------------------
    // 3. Job history
    // ------------------------------------------------------------------

    /**
     * @param  array{vessel_ids:?array, from:string, to:string, component:?string, rank_id:?int, job_type_id:?int, late:bool}  $f
     */
    public function historyQuery(array $f, ?string $sort = null, string $dir = 'desc'): Builder
    {
        $q = DB::table('pms_jobs as j')
            ->join('pms_job_definitions as d', 'd.id', '=', 'j.job_definition_id')
            ->join('pms_components as c', 'c.id', '=', 'j.component_id')
            ->join('vessels as v', 'v.id', '=', 'j.vessel_id')
            ->leftJoin('pms_components as p', 'p.id', '=', 'c.parent_id')
            ->leftJoin('pms_job_types as t', 't.id', '=', 'd.job_type_id')
            ->leftJoin('ranks as dr', 'dr.id', '=', 'j.done_by_rank_id')
            ->leftJoin('pms_conditions as ca', 'ca.id', '=', 'j.condition_after_id')
            ->leftJoin('users as au', 'au.id', '=', 'j.approved_by')
            ->whereIn('j.status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL])
            ->whereNotNull('j.completed_at')
            ->where('j.completed_at', '>=', $f['from'])
            // "up to and including" the to-date, whatever time part the column carries.
            ->where('j.completed_at', '<', CarbonImmutable::parse($f['to'])->addDay()->toDateString())
            ->when($f['vessel_ids'] !== null, fn ($q) => $q->whereIn('j.vessel_id', $f['vessel_ids']))
            ->when($f['rank_id'], fn ($q, $id) => $q->where('j.done_by_rank_id', $id))
            ->when($f['job_type_id'], fn ($q, $id) => $q->where('d.job_type_id', $id))
            ->when($f['component'], fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('c.name', 'like', '%'.$term.'%')->orWhere('p.name', 'like', '%'.$term.'%')))
            ->when($f['late'], fn ($q) => $q->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('j.due_date')->whereColumn('j.completed_at', '>', 'j.due_date'))
                ->orWhereNotNull('j.overdue_reason')))
            ->select([
                'j.id', 'j.vessel_id', 'j.component_id', 'j.status', 'j.completed_at', 'j.due_date', 'j.rh_at_completion',
                'j.overdue_reason', 'j.remark', 'j.approved_at',
                'v.name as vessel', 'd.name as job', 't.name as type', 'dr.code as done_by', 'ca.name as condition_name', 'au.name as approved_by',
            ]);

        $sort = isset(self::HISTORY_SORTS[$sort]) ? $sort : 'completed_at';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        return $q->orderBy(self::HISTORY_SORTS[$sort], $dir)->orderBy('j.id', $dir);
    }

    public function historyRows(iterable $records): array
    {
        return collect($records)->map(function ($r) {
            $completed = CarbonImmutable::parse($r->completed_at)->startOfDay();
            $due = $r->due_date ? CarbonImmutable::parse($r->due_date)->startOfDay() : null;
            $late = $due ? (int) $due->diffInDays($completed, false) : null;

            return [
                'job_id' => $r->id,
                'vessel_id' => $r->vessel_id,
                'completed_at' => $completed->toDateString(),
                'vessel' => $r->vessel,
                'path' => $this->path($r->vessel_id, $r->component_id, true),
                'job' => $r->job,
                'type' => $r->type,
                'done_by' => $r->done_by,
                'rh_at_completion' => $r->rh_at_completion !== null ? (float) $r->rh_at_completion : null,
                'due_date' => $due?->toDateString(),
                'days_late' => $late !== null && $late > 0 ? $late : null,
                'condition' => $r->condition_name,
                'overdue_reason' => $r->overdue_reason,
                'remark' => $r->remark,
                'approved' => $r->status === PmsJob::WAITING_APPROVAL
                    ? 'Waiting approval'
                    : ($r->approved_by ? $r->approved_by.' · '.CarbonImmutable::parse($r->approved_at)->format('d M Y H:i') : null),
            ];
        })->all();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** "PARENT › CHILD › COMPONENT", optionally led by the location - built from one load of the vessel's components. */
    private function path(int $vesselId, string $componentId, bool $withLocation): string
    {
        $all = $this->componentsByVessel[$vesselId] ??= DB::table('pms_components as c')
            ->leftJoin('pms_locations as l', 'l.id', '=', 'c.location_id')
            ->where('c.vessel_id', $vesselId)
            ->get(['c.id', 'c.name', 'c.parent_id', 'l.name as location'])->keyBy('id')->all();

        $names = [];
        for ($node = $all[$componentId] ?? null, $guard = 0; $node && $guard < 50; $guard++) {
            array_unshift($names, $node->name);
            $node = $node->parent_id ? ($all[$node->parent_id] ?? null) : null;
        }
        if ($withLocation && isset($all[$componentId]) && $all[$componentId]->location) {
            array_unshift($names, $all[$componentId]->location);
        }

        return implode(' › ', $names);
    }

    /** component id => latest meter reading (on or before today). */
    private function currentRh(array $componentIds): array
    {
        $tomorrow = CarbonImmutable::today()->addDay()->toDateString();
        $values = [];

        foreach (array_chunk($componentIds, 1000) as $chunk) {
            $latest = DB::table('pms_counter_readings')->whereIn('component_id', $chunk)->where('reading_date', '<', $tomorrow)
                ->groupBy('component_id')->select('component_id', DB::raw('max(reading_date) as latest'));

            DB::table('pms_counter_readings as r')
                ->joinSub($latest, 'm', fn ($j) => $j->on('m.component_id', '=', 'r.component_id')->on('m.latest', '=', 'r.reading_date'))
                ->get(['r.component_id', 'r.value'])
                ->each(function ($row) use (&$values) {
                    $values[$row->component_id] = (float) $row->value;
                });
        }

        return $values;
    }

    private function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    private function dateOnly($value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->toDateString() : null;
    }
}
