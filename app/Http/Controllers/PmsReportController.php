<?php

namespace App\Http\Controllers;

use App\Exports\PmsReportExport;
use App\Http\Controllers\Concerns\PmsAccess;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use App\Services\PmsReports;
use App\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * PMS reports (read-only). Same access as the rest of the PMS: shore roles
 * see any vessel or the whole fleet, a ship user only their own vessel -
 * never taken from the request. Every report takes ?export=1 to download
 * exactly what the page shows (all pages) as Excel.
 */
class PmsReportController extends Controller
{
    use PmsAccess;

    private const PER_PAGE = 50;

    public function __construct(private PmsReports $reports)
    {
        $this->middleware('auth');
    }

    // ------------------------------------------------------------------

    public function overview(Request $request)
    {
        $scope = $this->scope($request);
        $vessels = $scope['vessel'] ? collect([$scope['vessel']]) : $scope['vessels'];
        $data = $this->reports->overview($vessels);

        [$sort, $dir] = $this->sort($request, array_keys(PmsReports::OVERVIEW_COLUMNS), 'vessel', 'asc');
        $rows = $data['rows']->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE, $dir === 'desc')->values();

        if ($request->boolean('export')) {
            // A totals row only means something with more than one vessel.
            return $this->download('Vessel Overview', $scope, [], PmsReports::OVERVIEW_COLUMNS,
                $rows->count() > 1 ? array_merge($rows->all(), [$data['totals']]) : $rows->all(), [
                    'pct_overdue' => fn ($v) => $v === null ? '' : $v.'%',
                    'last_completed' => fn ($v) => $this->excelDate($v),
                ]);
        }

        return view('layouts.pms-report-overview', $scope + [
            'rows' => $this->paginate($rows, $request),
            'totals' => $data['totals'],
            'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function overdue(Request $request)
    {
        $scope = $this->scope($request);
        $f = [
            'vessel_ids' => $scope['vessel_ids'],
            'location_id' => $request->integer('location_id') ?: null,
            'rank_id' => $request->integer('rank_id') ?: null,
            'job_type_id' => $request->integer('job_type_id') ?: null,
            'critical' => $request->boolean('critical'),
            'days' => in_array($request->query('days'), ['30', '90'], true) ? $request->query('days') : null,
        ];
        [$sort, $dir] = $this->sort($request, array_keys(PmsReports::OVERDUE_SORTS), 'days', 'desc');
        $query = $this->reports->overdueQuery($f, $sort, $dir);

        if ($request->boolean('export')) {
            return $this->download('Overdue Jobs', $scope, $this->overdueFilterLabels($f), PmsReports::OVERDUE_COLUMNS,
                $this->reports->overdueRows($query->get()), [
                    'due_date' => fn ($v) => $this->excelDate($v),
                    'critical' => fn ($v) => $v ? 'Yes' : '',
                ]);
        }

        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $page->setCollection(collect($this->reports->overdueRows($page->getCollection())));

        return view('layouts.pms-report-overdue', $scope + [
            'rows' => $page,
            'summary' => $this->reports->overdueSummary($f),
            'filters' => $f,
            'sort' => $sort, 'dir' => $dir,
            'locations' => PmsLocation::ordered()->get(['id', 'name']),
            'ranks' => Rank::ordered()->get(['id', 'code', 'name']),
            'jobTypes' => PmsJobType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function history(Request $request)
    {
        $scope = $this->scope($request);
        $today = CarbonImmutable::today();
        $from = $this->dateParam($request->query('from')) ?? $today->subMonthsNoOverflow(12)->toDateString();
        $to = $this->dateParam($request->query('to')) ?? $today->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $f = [
            'vessel_ids' => $scope['vessel_ids'],
            'from' => $from,
            'to' => $to,
            'component' => trim((string) $request->query('component')) ?: null,
            'rank_id' => $request->integer('rank_id') ?: null,
            'job_type_id' => $request->integer('job_type_id') ?: null,
            'late' => $request->boolean('late'),
        ];
        [$sort, $dir] = $this->sort($request, array_keys(PmsReports::HISTORY_SORTS), 'completed_at', 'desc');
        $query = $this->reports->historyQuery($f, $sort, $dir);

        if ($request->boolean('export')) {
            return $this->download('Job History', $scope, $this->historyFilterLabels($f), PmsReports::HISTORY_COLUMNS,
                $this->reports->historyRows($query->get()), [
                    'completed_at' => fn ($v) => $this->excelDate($v),
                    'due_date' => fn ($v) => $this->excelDate($v),
                ]);
        }

        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $page->setCollection(collect($this->reports->historyRows($page->getCollection())));

        return view('layouts.pms-report-history', $scope + [
            'rows' => $page,
            'filters' => $f,
            'sort' => $sort, 'dir' => $dir,
            'ranks' => Rank::ordered()->get(['id', 'code', 'name']),
            'jobTypes' => PmsJobType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Which vessel(s) a report covers. A ship user is pinned to their own
     * vessel; a shore user picks one, or none for the whole fleet.
     *
     * @return array{vessels: \Illuminate\Support\Collection, vessel: ?Vessel, vessel_ids: ?array, locked: bool}
     */
    private function scope(Request $request): array
    {
        $this->authorizePmsView();

        if ($this->pmsIsShip()) {
            $own = Vessel::findOrFail(auth()->user()->role->vessel_id, ['id', 'name']);

            return ['vessels' => collect([$own]), 'vessel' => $own, 'vessel_ids' => [$own->id], 'locked' => true];
        }

        $vessels = Vessel::where('status', true)->orderBy('name')->get(['id', 'name']);
        $vessel = $request->filled('vessel_id') ? $vessels->firstWhere('id', (int) $request->query('vessel_id')) : null;

        return ['vessels' => $vessels, 'vessel' => $vessel, 'vessel_ids' => $vessel ? [$vessel->id] : null, 'locked' => false];
    }

    private function sort(Request $request, array $allowed, string $default, string $defaultDir): array
    {
        $sort = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : $default;
        $dir = in_array($request->query('dir'), ['asc', 'desc'], true) ? $request->query('dir') : $defaultDir;

        return [$sort, $dir];
    }

    private function paginate($rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, $request->integer('page', 1));

        return (new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]));
    }

    private function download(string $report, array $scope, array $filters, array $columns, array $rows, array $format)
    {
        $scopeName = $scope['vessel']->name ?? 'Fleet';
        $meta = ['Report' => $report, 'Vessel' => $scopeName] + $filters
            + ['Rows' => (string) count($rows), 'Exported' => now()->format('d/m/Y H:i')];
        $file = $report.' - '.preg_replace('/[^A-Za-z0-9 ._-]/', '', $scopeName).' - '.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PmsReportExport($report, $meta, $columns, $rows, $format), $file);
    }

    private function overdueFilterLabels(array $f): array
    {
        return array_filter([
            'Location' => $f['location_id'] ? PmsLocation::whereKey($f['location_id'])->value('name') : null,
            'Rank' => $f['rank_id'] ? Rank::whereKey($f['rank_id'])->value('code') : null,
            'Job type' => $f['job_type_id'] ? PmsJobType::whereKey($f['job_type_id'])->value('name') : null,
            'Critical only' => $f['critical'] ? 'Yes' : null,
            'Days overdue' => $f['days'] ? '> '.$f['days'] : null,
        ]);
    }

    private function historyFilterLabels(array $f): array
    {
        return array_filter([
            'Completed' => CarbonImmutable::parse($f['from'])->format('d/m/Y').' – '.CarbonImmutable::parse($f['to'])->format('d/m/Y'),
            'Component' => $f['component'],
            'Done by' => $f['rank_id'] ? Rank::whereKey($f['rank_id'])->value('code') : null,
            'Job type' => $f['job_type_id'] ? PmsJobType::whereKey($f['job_type_id'])->value('name') : null,
            'Only completed late' => $f['late'] ? 'Yes' : null,
        ]);
    }

    private function dateParam($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function excelDate(?string $value): string
    {
        return $value ? CarbonImmutable::parse($value)->format('d/m/Y') : '';
    }
}
