<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\PmsJob;
use App\PmsRequiredPart;
use App\Services\PmsDashboardCache;
use App\Services\PmsReports;
use App\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The PMS area's landing page: fleet or one vessel's key figures, what
 * needs attention, what's due next, and component condition - everything
 * read-only, everything cached per PmsDashboardCache.
 */
class PmsDashboardController extends Controller
{
    use PmsAccess;

    const DUE_SOON_WINDOW_DAYS = 14;

    const STALE_COUNTER_DAYS = 7;

    public function __construct(private PmsReports $reports)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePmsView();

        if ($this->pmsIsShip()) {
            $vessel = Vessel::findOrFail(auth()->user()->role->vessel_id, ['id', 'name']);
            $vessels = collect([$vessel]);
        } else {
            $vessels = Vessel::where('status', true)->orderBy('name')->get(['id', 'name']);
            $vessel = $request->filled('vessel_id') ? $vessels->firstWhere('id', (int) $request->query('vessel_id')) : null;
        }

        // null $vessel (shore only) = the whole fleet.
        $scopeVessels = $vessel ? collect([$vessel]) : $vessels;
        $vesselIds = $scopeVessels->pluck('id')->all();
        $cacheKey = $vessel->id ?? null;

        $overview = PmsDashboardCache::remember($cacheKey, 'overview', fn () => $this->reports->overview($scopeVessels));
        $kpis = PmsDashboardCache::remember($cacheKey, 'kpis', fn () => $this->kpis($vesselIds, $overview, $vessel));
        $attention = PmsDashboardCache::remember($cacheKey, 'attention', fn () => $this->attention($vesselIds, $overview, $vessel));
        $dueNext = PmsDashboardCache::remember($cacheKey, 'due-next', fn () => $this->dueNext($vesselIds));
        $conditions = PmsDashboardCache::remember($cacheKey, 'conditions', fn () => $this->conditions($vesselIds));
        $completedPerMonth = PmsDashboardCache::remember($cacheKey, 'completed-per-month', fn () => $this->completedPerMonth($vesselIds));
        $dueByWeek = PmsDashboardCache::remember($cacheKey, 'due-by-week', fn () => $this->dueByWeek($vesselIds));
        $overdueByAge = PmsDashboardCache::remember($cacheKey, 'overdue-by-age', fn () => $this->overdueByAge($scopeVessels));

        return view('layouts.pms-dashboard', [
            'vessel' => $vessel,
            'vessels' => $vessels,
            'fleet' => $vessel === null,
            'kpis' => $kpis,
            'overview' => $overview,
            'attention' => $attention,
            'dueNext' => $dueNext,
            'conditions' => $conditions,
            'completedPerMonth' => $completedPerMonth,
            'dueByWeek' => $dueByWeek,
            'overdueByAge' => $overdueByAge,
            'generatedAt' => now(),
        ]);
    }

    /** The 5 headline tiles. */
    private function kpis(array $vesselIds, array $overview, ?Vessel $vessel): array
    {
        $open = (int) $overview['totals']['open'];
        $overdue = (int) $overview['totals']['overdue'];
        $dueSoon = (int) $overview['totals']['due_soon'];
        $pct = $open > 0 ? round($overdue / $open * 100, 1) : null;

        $today = CarbonImmutable::today();
        $due14 = PmsJob::whereIn('vessel_id', $vesselIds)
            ->whereIn('status', [PmsJob::PLANNED, PmsJob::DUE_SOON])
            ->whereBetween('due_date', [$today->toDateString(), $today->addDays(self::DUE_SOON_WINDOW_DAYS)->toDateString()])
            ->count();

        $completed30 = PmsJob::whereIn('vessel_id', $vesselIds)
            ->whereIn('status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL])
            ->where('completed_at', '>=', $today->subDays(30)->toDateString())
            ->count();

        return [
            ['label' => 'Open jobs', 'value' => $open, 'sub' => 'All open jobs', 'color' => 'ink',
                'href' => route('pms.jobs.index', $this->withVessel($vessel, ['filter' => 'all']))],
            ['label' => 'Overdue', 'value' => $overdue, 'sub' => $pct !== null ? $pct.'% of open jobs' : 'No open jobs', 'color' => 'danger',
                'href' => route('pms.jobs.index', $this->withVessel($vessel, ['filter' => 'overdue']))],
            ['label' => 'Due soon', 'value' => $dueSoon, 'sub' => 'Inside warning period', 'color' => 'warn',
                'href' => route('pms.jobs.index', $this->withVessel($vessel, ['filter' => 'duesoon']))],
            ['label' => 'Due in next 14 days', 'value' => $due14, 'sub' => 'Plan these now', 'color' => 'accent',
                'href' => route('pms.jobs.index', $this->withVessel($vessel, ['filter' => 'due14']))],
            ['label' => 'Completed, last 30 days', 'value' => $completed30, 'sub' => 'Across the selection', 'color' => 'ink',
                'href' => route('pms.reports.history', $this->withVessel($vessel, ['from' => $today->subDays(30)->toDateString(), 'to' => $today->toDateString()]))],
        ];
    }

    /** Counts + links for things that need a human to act. */
    private function attention(array $vesselIds, array $overview, ?Vessel $vessel): array
    {
        $staleCounters = (int) $overview['totals']['stale_counters'];
        $criticalOverdue = (int) $overview['totals']['critical_overdue'];
        $approval = (int) $overview['totals']['approval'];

        $openJobIds = PmsJob::whereIn('vessel_id', $vesselIds)->whereIn('status', PmsJob::OPEN_STATUSES)->pluck('id');
        $unrequisitioned = PmsRequiredPart::whereNotNull('job_id')->whereIn('job_id', $openJobIds)->whereNull('order_id')->count();

        $badComponents = DB::table('pms_components as c')
            ->join('pms_conditions as k', 'k.id', '=', 'c.condition_id')
            ->whereIn('c.vessel_id', $vesselIds)->where('c.is_inactive', false)->whereNull('c.deleted_at')
            ->where('k.name', 'Bad')->count();

        return [
            [
                'value' => $staleCounters, 'label' => 'Counters not updated',
                'sub' => 'No reading for more than '.self::STALE_COUNTER_DAYS.' days',
                'bg' => 'danger', 'href' => route('pms.counters.index', $this->withVessel($vessel, [])),
            ],
            [
                'value' => $criticalOverdue, 'label' => 'Critical jobs overdue', 'sub' => 'Critical equipment',
                'bg' => 'danger', 'href' => route('pms.reports.overdue', $this->withVessel($vessel, ['critical' => 1])),
            ],
            [
                'value' => $approval, 'label' => 'Waiting office approval', 'sub' => 'Completed jobs to sign off',
                'bg' => 'neutral', 'href' => route('pms.jobs.index', $this->withVessel($vessel, ['filter' => 'approval'])),
            ],
            [
                'value' => $unrequisitioned, 'label' => 'Parts not requisitioned', 'sub' => 'Required parts of open jobs',
                'bg' => 'warn', 'href' => route('pms.parts.index', $this->withVessel($vessel, ['filter' => 'pending'])),
            ],
            [
                'value' => $badComponents, 'label' => 'Components in Bad condition', 'sub' => 'Reported at last job completion',
                'bg' => 'warn', 'href' => route('pms.components.index', $this->withVessel($vessel, [])),
            ],
        ];
    }

    /** Next 10 open jobs by due date, across the scope. */
    private function dueNext(array $vesselIds): array
    {
        return PmsJob::whereIn('vessel_id', $vesselIds)
            ->whereIn('status', PmsJob::ACTIVE_STATUSES)
            ->whereNotNull('due_date')
            ->with(['vessel:id,name', 'definition:id,name', 'component:id,name'])
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn (PmsJob $job) => [
                'vessel' => $job->vessel->name ?? '',
                'job' => $job->definition->name ?? '',
                'component' => $job->component->name ?? '',
                'due_date' => $job->due_date->toDateString(),
            ])->all();
    }

    /** Component condition distribution across the scope. */
    private function conditions(array $vesselIds): array
    {
        $counts = DB::table('pms_components as c')
            ->join('pms_conditions as k', 'k.id', '=', 'c.condition_id')
            ->whereIn('c.vessel_id', $vesselIds)->where('c.is_inactive', false)->whereNull('c.deleted_at')
            ->groupBy('k.name')->pluck(DB::raw('count(*)'), 'k.name');

        $total = (int) $counts->sum();
        $order = ['Good', 'Fair', 'Bad', 'Not Classified'];

        return collect($order)->map(function ($name) use ($counts, $total) {
            $value = (int) ($counts[$name] ?? 0);

            return [
                'label' => $name, 'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0,
            ];
        })->all();
    }

    /** A fleet-scope link omits vessel_id - Reports shows the whole fleet then; Jobs/Counters/Components fall back to their own default vessel. */
    private function withVessel(?Vessel $vessel, array $extra): array
    {
        return $vessel ? ['vessel_id' => $vessel->id] + $extra : $extra;
    }

    // ------------------------------------------------------------------
    // Charts - plain HTML/CSS bars, see layouts.pms-dashboard. Each bar's
    // own count/date math is done in PHP, not DB-specific date functions
    // (DATEDIFF, strftime...), the same portability rule PmsReports follows -
    // this app's tests run on SQLite, production on MySQL.
    // ------------------------------------------------------------------

    /** Completed jobs per month, the last 12 months - the current month included, so far. */
    private function completedPerMonth(array $vesselIds): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth()->subMonths(11);

        $counts = PmsJob::whereIn('vessel_id', $vesselIds)
            ->whereIn('status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL])
            ->where('completed_at', '>=', $start->toDateString())
            ->pluck('completed_at')
            ->countBy(fn ($date) => $date->format('Y-m'));

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->addMonths($i);
            $months[] = [
                'value' => (int) ($counts[$m->format('Y-m')] ?? 0),
                'short' => $m->format('M'),
                'label' => $m->format('F Y'),
                'current' => $m->isSameMonth($today),
            ];
        }

        return $this->scaleBars($months, fn ($row) => $row['value'].' completed, '.$row['label'].($row['current'] ? ' (month in progress)' : ''));
    }

    /** Open jobs by due date, bucketed into the next 12 weeks from today. */
    private function dueByWeek(array $vesselIds): array
    {
        $today = CarbonImmutable::today();
        $end = $today->addWeeks(12);

        $dueDates = PmsJob::whereIn('vessel_id', $vesselIds)
            ->whereIn('status', PmsJob::ACTIVE_STATUSES)
            ->whereBetween('due_date', [$today->toDateString(), $end->subDay()->toDateString()])
            ->pluck('due_date');

        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $weekStart = $today->addWeeks($i);
            $weekEnd = $weekStart->addDays(6);
            $value = $dueDates->filter(fn ($d) => $d->toDateString() >= $weekStart->toDateString() && $d->toDateString() <= $weekEnd->toDateString())->count();
            $weeks[] = [
                'value' => $value,
                'short' => $weekStart->format('d M'),
                'label' => 'Week of '.$weekStart->format('d M Y'),
                'current' => $i === 0,
            ];
        }

        return $this->scaleBars($weeks, fn ($row) => $row['value'].' due, '.$row['label']);
    }

    /** Scales a list of {value,...} rows to bar-height percentages, 3 gridlines, and a hover/focus readout string. */
    private function scaleBars(array $rows, \Closure $readout): array
    {
        $max = max(1, collect($rows)->max('value'));

        return [
            'bars' => array_map(fn ($row) => $row + ['pct' => round($row['value'] / $max * 100, 1), 'readout' => $readout($row)], $rows),
            'gridlines' => [
                ['pct' => 0, 'value' => 0],
                ['pct' => 50, 'value' => (int) round($max / 2)],
                ['pct' => 100, 'value' => $max],
            ],
        ];
    }

    /**
     * Overdue jobs by how late, one stacked bar per vessel in scope (or the
     * one vessel). Jobs overdue purely on running hours (no due_date) can't
     * be dated, so they're left out of this chart.
     */
    private function overdueByAge(Collection $scopeVessels): array
    {
        $today = CarbonImmutable::today();
        $buckets = [
            ['label' => '0–30 days', 'color' => '#EE9F96'],
            ['label' => '31–90 days', 'color' => '#DB6B5F'],
            ['label' => '91–365 days', 'color' => '#B3261E'],
            ['label' => 'Over 365 days', 'color' => '#6E1611'],
        ];

        $overdue = PmsJob::whereIn('vessel_id', $scopeVessels->pluck('id'))
            ->where('status', PmsJob::OVERDUE)->whereNotNull('due_date')
            ->get(['vessel_id', 'due_date'])
            ->groupBy('vessel_id');

        $rows = $scopeVessels->map(function ($vessel) use ($overdue, $today, $buckets) {
            $counts = array_fill(0, count($buckets), 0);
            foreach ($overdue->get($vessel->id, []) as $job) {
                $days = (int) $job->due_date->diffInDays($today);
                $bucket = $days <= 30 ? 0 : ($days <= 90 ? 1 : ($days <= 365 ? 2 : 3));
                $counts[$bucket]++;
            }

            return ['vessel' => $vessel->name, 'counts' => $counts, 'total' => array_sum($counts)];
        })->values();

        $maxTotal = max(1, $rows->max('total'));

        return [
            'rows' => $rows->map(function ($row) use ($buckets, $maxTotal) {
                $row['segments'] = [];
                foreach ($buckets as $i => $bucket) {
                    if ($row['counts'][$i] <= 0) {
                        continue;
                    }
                    $row['segments'][] = $bucket + [
                        'value' => $row['counts'][$i],
                        'pct' => round($row['counts'][$i] / $maxTotal * 100, 1),
                        'readout' => $row['counts'][$i].' overdue '.$bucket['label'].' on '.$row['vessel'],
                    ];
                }

                return $row;
            })->all(),
            'buckets' => $buckets,
        ];
    }
}
