@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">PMS Jobs &mdash; {{ $vessel->name }}</strong>
			<div class="right-buttons">
				<a href="{{ route('pms.jobs.export', ['vessel_id' => $vessel->id]) }}" class="btn btn-sm btn-success" id="pms-jobs-export"><i class="fas fa-file-excel"></i> Export Excel</a>
			</div>
		</div>
		<div class="card-body">
			<div class="pms-toolbar">
				@if($vessels->isNotEmpty())
				<form method="GET" action="{{ route('pms.jobs.index') }}">
					<select name="vessel_id" class="form-control" id="pms-vessel-picker" aria-label="Vessel">
						@foreach($vessels as $v)
						<option value="{{ $v->id }}" {{ $v->id === $vessel->id ? 'selected' : '' }}>{{ $v->name }}</option>
						@endforeach
					</select>
				</form>
				@endif
				<div class="pms-filters" role="group" aria-label="Filter jobs">
					<button type="button" class="pms-filter active" data-filter="all">All <b>…</b></button>
					<button type="button" class="pms-filter" data-filter="overdue">Overdue <b>…</b></button>
					<button type="button" class="pms-filter" data-filter="duesoon">Due Soon <b>…</b></button>
					<button type="button" class="pms-filter" data-filter="approval">Waiting Approval <b>…</b></button>
					<button type="button" class="pms-filter" data-filter="critical">Critical <b>…</b></button>
				</div>
				<span class="spacer"></span>
				<input type="search" class="form-control pms-search" id="pms-jobs-search" placeholder="Search job, component, rank, type…" aria-label="Search jobs">
			</div>

			@if(!$canEdit)
			<p class="pms-note"><i class="fas fa-eye"></i> View only &mdash; jobs are completed and rescheduled on board.{{ $canApprove ? ' You can approve completed jobs.' : '' }}</p>
			@endif

			<div class="pms-jobs-layout">
				<nav class="pms-jobs-tree" aria-label="Components">
					<button type="button" class="pms-jt-all is-selected" id="pms-jt-all">
						<span class="pms-jt-name">All components</span>
						<span class="pms-jt-count" id="pms-jt-all-count">…</span>
					</button>
					@foreach($locations as $location)
					@php $roots = $rootsByLocation->get($location->id, collect()); @endphp
					@if($roots->isNotEmpty())
					<div class="pms-jt-loc">{{ $location->name }}</div>
					<ul class="pms-jt">
						@foreach($roots as $node)
						@include('partials.pms-job-tree-node', ['node' => $node])
						@endforeach
					</ul>
					@endif
					@endforeach
					@if($rootsByLocation->isEmpty())
					<p class="pms-location-empty">No components on this vessel yet.</p>
					@endif
				</nav>

				<div>
					<p class="pms-note mb-2" id="pms-jobs-scope"></p>
					<div class="table-responsive">
						<table id="pms-jobs-table" class="table table-bordered table-hover table-sm" style="width:100%;">
							<thead>
								<tr>
									<th>Rank</th>
									<th>Job Name</th>
									<th>Component</th>
									<th>Type</th>
									<th>Due Date</th>
									<th class="pms-num">Due RH</th>
									<th class="pms-num">Current RH</th>
									<th>Last Done</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Job dialog --}}
<div class="modal fade" id="pms-job-modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
				<legend class="modal-title"><i class="fas fa-wrench"></i> &nbsp; <span id="pj-title">Job</span></legend>
				<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
			</div>
			<div class="modal-body">
				<ul class="nav nav-tabs mb-3" role="tablist">
					<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#pj-tab-details" role="tab">Details</a></li>
					<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#pj-tab-parts" role="tab"><i class="fas fa-cubes"></i> Required Parts <span id="pj-parts-count" class="pms-muted"></span></a></li>
					<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#pj-tab-history" role="tab"><i class="fas fa-history"></i> History <span id="pj-history-count" class="pms-muted"></span></a></li>
				</ul>
				<div class="tab-content">
					<div class="tab-pane fade show active" id="pj-tab-details" role="tabpanel">
						<div id="pj-details"></div>

						<form id="pj-complete-form" class="pms-jd-panel pms-hidden" autocomplete="off" novalidate>
							<h6 class="pms-section mt-0">Mark as Completed</h6>
							<div class="pms-form-grid">
								<div class="form-group">
									<label for="pj-completed-at">Completed On <span class="text-danger">*</span></label>
									<input type="date" class="form-control" id="pj-completed-at" name="completed_at" required>
								</div>
								<div class="form-group">
									<label for="pj-done-by">Done By (Rank) <span class="text-danger">*</span></label>
									<select class="form-control" id="pj-done-by" name="done_by_rank_id" required>
										<option value="" disabled selected>Select…</option>
										@foreach($ranks as $rank)
										<option value="{{ $rank->id }}">{{ $rank->code }} &mdash; {{ $rank->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="form-group" id="pj-rh-group">
									<label for="pj-rh">Running Hours at Completion <span class="text-danger">*</span></label>
									<input type="number" class="form-control" id="pj-rh" name="rh_at_completion" min="0" step="0.1">
								</div>
								<div class="form-group">
									<label for="pj-condition">Condition After <span class="text-danger">*</span></label>
									<select class="form-control" id="pj-condition" name="condition_after_id" required>
										@foreach($conditions as $condition)
										<option value="{{ $condition->id }}">{{ $condition->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="form-group full">
									<label for="pj-remark">Remark</label>
									<textarea class="form-control" id="pj-remark" name="remark" rows="2" maxlength="5000"></textarea>
								</div>
								<div class="form-group full" id="pj-used-group">
									<label for="pj-used-search">Parts used from stock</label>
									<div class="pp-search-wrap">
										<input type="search" class="form-control" id="pj-used-search" placeholder="Search the vessel's stock by name or article no.…">
										<div class="pp-results pms-hidden" id="pj-used-results" style="top:40px;"></div>
									</div>
									<div id="pj-used-list" class="mt-2"></div>
									<small class="form-text pms-muted">Taken out of the vessel's stock when you save, and logged as a "PMS job" consumption. You can't use more than what is on board.</small>
								</div>
								<div class="form-group full pms-hidden" id="pj-checklist-group">
									<label>Checklist <span class="text-danger">*</span> <span class="pms-muted" style="font-weight:400;">&mdash; tick every item</span></label>
									<div id="pj-checklist"></div>
								</div>
								<div class="full pms-hidden" id="pj-late-group">
									<div class="alert alert-warning py-2 mb-2"><i class="fas fa-exclamation-triangle"></i> Completed after it was due &mdash; give the reason.</div>
									<div class="pms-form-grid">
										<div class="form-group">
											<label for="pj-overdue-reason">Overdue Reason <span class="text-danger">*</span></label>
											<select class="form-control" id="pj-overdue-reason" name="overdue_reason">
												<option value="" selected>Select…</option>
												@foreach(\App\PmsJob::RESCHEDULE_REASONS as $reason)
												<option value="{{ $reason }}">{{ $reason }}</option>
												@endforeach
											</select>
										</div>
										<div class="form-group">
											<label for="pj-overdue-remark">Overdue Remark</label>
											<input type="text" class="form-control" id="pj-overdue-remark" name="overdue_remark" maxlength="5000">
										</div>
									</div>
								</div>
							</div>
							<div class="text-right">
								<button type="button" class="btn btn-secondary btn-sm pj-cancel">Cancel</button>
								<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Save Completion</button>
							</div>
						</form>

						<form id="pj-reschedule-form" class="pms-jd-panel pms-hidden" autocomplete="off" novalidate>
							<h6 class="pms-section mt-0">Reschedule</h6>
							<p class="pms-note mb-2">Moves the due date only &mdash; the original due date stays on record.</p>
							<div class="pms-form-grid">
								<div class="form-group">
									<label for="pj-new-due">New Due Date <span class="text-danger">*</span></label>
									<input type="date" class="form-control" id="pj-new-due" name="due_date" required>
								</div>
								<div class="form-group">
									<label for="pj-reason">Reason <span class="text-danger">*</span></label>
									<select class="form-control" id="pj-reason" name="reason" required>
										<option value="" disabled selected>Select…</option>
										@foreach(\App\PmsJob::RESCHEDULE_REASONS as $reason)
										<option value="{{ $reason }}">{{ $reason }}</option>
										@endforeach
									</select>
								</div>
								<div class="form-group full pms-hidden" id="pj-new-rh-group">
									<label for="pj-new-rh">New Due Running Hours</label>
									<input type="number" class="form-control" id="pj-new-rh" name="due_running_hours" min="0" step="0.1">
									<small class="form-text pms-muted" id="pj-new-rh-hint"></small>
								</div>
								<div class="form-group full">
									<label for="pj-reschedule-remark">Remark</label>
									<textarea class="form-control" id="pj-reschedule-remark" name="remark" rows="2" maxlength="5000"></textarea>
								</div>
							</div>
							<div class="text-right">
								<button type="button" class="btn btn-secondary btn-sm pj-cancel">Cancel</button>
								<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-calendar-alt"></i> Save New Date</button>
							</div>
						</form>
					</div>
					<div class="tab-pane fade" id="pj-tab-parts" role="tabpanel">
						<div class="table-responsive">
							<table class="table table-sm table-bordered pp-table mb-2">
								<thead><tr><th style="width:28px;"></th><th>Part</th><th class="pms-num">Qty</th><th class="pms-num">Est.</th><th>Requisition</th><th class="action"></th></tr></thead>
								<tbody id="pj-parts"></tbody>
							</table>
						</div>
						<div class="d-flex justify-content-between flex-wrap" style="gap:8px;">
							<button type="button" class="btn btn-sm btn-outline-secondary pms-hidden" id="pj-add-part"><i class="fas fa-plus"></i> Add part</button>
							<button type="button" class="btn btn-sm btn-primary pms-hidden" id="pj-create-req"><i class="fas fa-cart-plus"></i> Create Requisition</button>
						</div>
						<p class="pms-note mt-2 mb-0 pms-hidden" id="pj-req-note">Tick the parts to order, then Create Requisition. It opens the requisition form for this vessel with those parts already on it; a part already on a requisition can't be ordered again.</p>
					</div>
					<div class="tab-pane fade" id="pj-tab-history" role="tabpanel">
						<div id="pj-history"></div>
					</div>
				</div>
			</div>
			<div class="modal-footer justify-content-between">
				<div>
					<button type="button" class="btn btn-outline-secondary btn-sm pms-hidden" id="pj-edit-def"><i class="fas fa-edit"></i> Edit Definition</button>
				</div>
				<div>
					<button type="button" class="btn btn-primary btn-sm pms-hidden" id="pj-open-reschedule"><i class="fas fa-calendar-alt"></i> Reschedule</button>
					<button type="button" class="btn btn-success btn-sm pms-hidden" id="pj-open-complete"><i class="fas fa-check"></i> Mark as Completed</button>
					<button type="button" class="btn btn-success btn-sm pms-hidden" id="pj-approve"><i class="fas fa-check-circle"></i> Approve</button>
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
</div>

@if($canEdit)
@include('partials.pms-job-definition-modal')
@endif
@include('partials.pms-part-modal')
@endsection

@section('home-js')
@include('partials.pms-part-script')
@if($canEdit)
@include('partials.pms-job-definition-script')
@endif
<script>
$(function () {
	var vesselId = @json($vessel->id);
	var dataUrl = '{{ route('pms.jobs.data') }}';
	var exportUrl = '{{ route('pms.jobs.export') }}';
	var jobsUrl = '{{ url('/pms/jobs') }}';
	var csrf = '{{ csrf_token() }}';
	var today = '{{ now()->toDateString() }}';
	var tomorrow = '{{ now()->addDay()->toDateString() }}';
	var defaultConditionId = @json($defaultConditionId);
	var statusLabels = { Planned: 'Planned', DueSoon: 'Due Soon', Overdue: 'Overdue', WaitingApproval: 'Waiting Approval', Completed: 'Completed', Cancelled: 'Cancelled' };
	var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

	var rows = [];
	var state = { filter: 'all', component: @json($selectedComponent), q: '' };
	var current = null; // the job open in the dialog

	function esc(text) { return $('<div>').text(text == null ? '' : String(text)).html(); }
	function fmtDate(iso) {
		if (!iso) { return '—'; }
		var p = iso.split('-');
		return p[2] + ' ' + months[parseInt(p[1], 10) - 1] + ' ' + p[0];
	}
	function fmtRh(v) { return v === null || v === undefined ? '—' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 }); }
	function status(s) { return '<span class="pms-status pms-status-' + s + '">' + esc(statusLabels[s] || s) + '</span>'; }
	function errorText(xhr) {
		var errors = xhr.responseJSON && xhr.responseJSON.errors;
		return errors ? Object.values(errors)[0][0] : ((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
	}

	$('#pms-vessel-picker').on('change', function () { this.form.submit(); });

	// ---- Tree ------------------------------------------------------------
	// parent of every component, read off the rendered tree, so counts can be
	// rolled up and a selection can take in everything beneath it.
	var parentOf = {};
	$('.pms-jt li[data-id]').each(function () {
		var $parent = $(this).parent().closest('li[data-id]');
		parentOf[$(this).attr('data-id')] = $parent.length ? $parent.attr('data-id') : null;
	});

	function scopeIds(id) {
		var ids = {};
		ids[id] = true;
		$('.pms-jt li[data-id="' + id + '"] li[data-id]').each(function () { ids[$(this).attr('data-id')] = true; });
		return ids;
	}

	$('.pms-jobs-tree').on('click', '.pms-jt-caret', function () {
		$(this).closest('li').toggleClass('collapsed');
	});
	$('.pms-jobs-tree').on('click', '.pms-jt-label', function () {
		selectComponent($(this).attr('data-id'));
	});
	$('#pms-jt-all').on('click', function () { selectComponent(null); });

	function selectComponent(id) {
		state.component = id;
		$('.pms-jt-label, #pms-jt-all').removeClass('is-selected');
		if (id) {
			var $label = $('.pms-jt-label[data-id="' + id + '"]').addClass('is-selected');
			$label.parents('li.collapsed').removeClass('collapsed');
		} else {
			$('#pms-jt-all').addClass('is-selected');
		}
		render();
	}

	// ---- Filters / search -------------------------------------------------
	function matchesFilter(r, filter) {
		switch (filter) {
			case 'overdue': return r.status === 'Overdue';
			case 'duesoon': return r.status === 'DueSoon';
			case 'approval': return r.status === 'WaitingApproval';
			case 'critical': return r.critical;
			default: return true;
		}
	}
	function matchesSearch(r) { return !state.q || r.search.indexOf(state.q) !== -1; }

	$('.pms-filter').on('click', function () {
		state.filter = $(this).attr('data-filter');
		$('.pms-filter').removeClass('active');
		$(this).addClass('active');
		render();
	});
	$('#pms-jobs-search').on('input', function () {
		state.q = $.trim($(this).val()).toLowerCase();
		render();
	});

	// ---- Table -------------------------------------------------------------
	var table = $('#pms-jobs-table').DataTable({
		data: [],
		dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
		pageLength: 50,
		order: [[4, 'asc']],
		// Until /pms/jobs/data answers, an empty table means "not here yet", not "no jobs".
		language: { emptyTable: 'Loading…', info: '_TOTAL_ jobs', infoEmpty: 'Loading…', infoFiltered: '' },
		rowId: 'id',
		columns: [
			{ data: 'rank', render: function (v, t, r) { return t === 'display' ? '<span title="' + esc(r.rank_name) + '">' + esc(v) + '</span>' : v; } },
			{ data: 'name', render: function (v, t, r) {
				if (t !== 'display') { return v; }
				return esc(v) + (r.critical ? ' <span class="pms-flag pms-flag-critical">Critical</span>' : '');
			} },
			{ data: 'component', render: function (v, t, r) {
				if (t !== 'display') { return v; }
				return esc(v) + (r.component_parent ? '<span class="pms-job-sub">' + esc(r.component_parent) + '</span>' : '');
			} },
			{ data: 'type', render: function (v, t, r) { return t === 'display' ? '<span title="' + esc(r.type_name) + '">' + esc(v) + '</span>' : v; } },
			{ data: 'due_date', render: function (v, t, r) {
				if (t === 'sort' || t === 'type') { return v || '9999-12-31'; }
				if (t !== 'display') { return v; }
				var html = '<span class="' + (r.status === 'Overdue' ? 'pms-overdue-date' : '') + '">' + fmtDate(v) + '</span>';
				return html + (r.rescheduled ? ' <i class="fas fa-redo pms-muted" title="Rescheduled"></i>' : '');
			} },
			{ data: 'due_running_hours', className: 'pms-num', render: function (v, t) { return t === 'display' ? fmtRh(v) : (v === null ? -1 : v); } },
			{ data: 'current_rh', className: 'pms-num', render: function (v, t) { return t === 'display' ? fmtRh(v) : (v === null ? -1 : v); } },
			{ data: 'last_done', render: function (v, t) { return t === 'display' ? fmtDate(v) : (v || ''); } },
			{ data: 'status', render: function (v, t) { return t === 'display' ? status(v) : v; } },
		],
	});

	function render() {
		var scope = state.component ? scopeIds(state.component) : null;

		// Filter pill counts: within the selected component and search.
		var pillCounts = { all: 0, overdue: 0, duesoon: 0, approval: 0, critical: 0 };
		// Tree counts: within the active filter and search, rolled up the tree.
		var treeCounts = {}, total = 0;
		var shown = [];

		$.each(rows, function (_, r) {
			if (!matchesSearch(r)) { return; }
			var inScope = !scope || scope[r.component_id];
			if (inScope) {
				$.each(pillCounts, function (f) { if (matchesFilter(r, f)) { pillCounts[f]++; } });
			}
			if (!matchesFilter(r, state.filter)) { return; }
			total++;
			for (var id = r.component_id; id; id = parentOf[id]) { treeCounts[id] = (treeCounts[id] || 0) + 1; }
			if (inScope) { shown.push(r); }
		});

		$('.pms-filter').each(function () { $(this).find('b').text(pillCounts[$(this).attr('data-filter')]); });
		$('.pms-jt-label').each(function () {
			var n = treeCounts[$(this).attr('data-id')] || 0;
			$(this).find('.pms-jt-count').text(n).toggleClass('zero', n === 0);
		});
		$('#pms-jt-all-count').text(total);

		var label = state.component ? $('.pms-jt-label[data-id="' + state.component + '"] .pms-jt-name').text() : null;
		$('#pms-jobs-scope').html(label ? 'Showing jobs on <strong>' + esc(label) + '</strong> and everything under it.' : '');

		table.clear().rows.add(shown).draw();

		var params = { vessel_id: vesselId, filter: state.filter };
		if (state.component) { params.component_id = state.component; }
		if (state.q) { params.q = state.q; }
		$('#pms-jobs-export').attr('href', exportUrl + '?' + $.param(params));
	}

	function load() {
		return $.getJSON(dataUrl, { vessel_id: vesselId }).done(function (res) {
			rows = res.data;
			var lang = table.settings()[0].oLanguage;
			lang.sEmptyTable = 'No jobs to show.';
			lang.sInfoEmpty = '0 jobs';
			render();
			$(document).trigger('pms-jobs-loaded');
		}).fail(function (xhr) {
			table.settings()[0].oLanguage.sEmptyTable = 'Could not load the jobs.';
			table.draw(false);
			toastr.error(errorText(xhr));
		});
	}

	$('#pms-jobs-table tbody').on('click', 'tr', function () {
		var row = table.row(this).data();
		if (row) { openJob(row.id); }
	});

	// ---- Job dialog ----------------------------------------------------------
	var $modal = $('#pms-job-modal');

	function dl(items) {
		return '<dl class="pms-jd-dl">' + $.map(items, function (item) {
			if (item[1] === null || item[1] === undefined || item[1] === '') { return ''; }
			return '<div><dt>' + esc(item[0]) + '</dt><dd>' + (item[2] ? item[1] : esc(item[1])) + '</dd></div>';
		}).join('') + '</dl>';
	}

	function openJob(id) {
		$.getJSON(jobsUrl + '/' + id).done(function (res) {
			current = res;
			var job = res.job, def = res.definition, comp = res.component;
			$('#pj-title').text(def.name);

			var flags = [];
			if (def.is_critical) { flags.push('<span class="pms-flag pms-flag-critical">Critical</span>'); }
			if (def.is_class_relevant) { flags.push('<span class="pms-flag pms-flag-class">Class</span>'); }
			if (def.is_safety_relevant) { flags.push('<span class="pms-flag pms-flag-counter">Safety</span>'); }
			if (def.requires_approval) { flags.push('<span class="pms-flag pms-flag-inactive">Needs approval</span>'); }
			if (!def.is_active) { flags.push('<span class="pms-flag pms-flag-inactive">Definition inactive</span>'); }

			var html = '<p class="mb-2">' + status(job.status) + ' ' + flags.join(' ') + '</p>' +
				'<p class="pms-note mb-3"><i class="fas fa-cog"></i> ' + esc(comp.path) + '</p>';
			html += dl([
				['Due Date', '<span class="' + (job.status === 'Overdue' ? 'pms-overdue-date' : '') + '">' + fmtDate(job.due_date) + '</span>', true],
				['Original Due', job.original_due_date !== job.due_date ? fmtDate(job.original_due_date) : null],
				['Warning From', job.warning_date ? fmtDate(job.warning_date) : null],
				['Due RH', job.due_running_hours !== null ? fmtRh(job.due_running_hours) + ' h' : null],
				['Current RH', comp.current_rh !== null ? fmtRh(comp.current_rh) + ' h' + (comp.reading_date ? ' (' + comp.reading_date + ')' : '') : null],
				['Frequency', def.frequency],
				['Job Type', def.type],
				['Responsible', def.rank],
				['Performed By', def.performed_by === 'ClassSurveyor' ? 'Class Surveyor' : def.performed_by],
				['Perform When', ({ AtSea: 'At Sea', InPort: 'In Port' })[def.perform_when] || def.perform_when],
				['Staff Needed', def.staff_needed],
				['Time Needed', def.time_needed],
				['Class Code', def.class_code],
				['Internal Code', def.internal_code],
				['Rescheduled', job.reschedule_reason ? job.reschedule_reason + (job.reschedule_remark ? ' — ' + job.reschedule_remark : '') : null],
			]);
			if (job.status === 'WaitingApproval') {
				html += '<div class="alert alert-info py-2">Completed ' + fmtDate(job.completed_at) + (job.done_by ? ' by ' + esc(job.done_by) : '') +
					(job.completed_by ? ' (entered by ' + esc(job.completed_by) + ')' : '') + ' &mdash; waiting for office approval.' +
					(job.remark ? '<br>' + esc(job.remark) : '') + '</div>';
			}
			var orders = {};
			$.each(res.parts, function (_, p) { if (p.order) { orders[p.order.id] = p.order; } });
			var orderList = $.map(orders, function (o) { return '<a href="' + esc(o.url) + '">' + esc(o.req_no || 'Draft') + '</a> &mdash; ' + esc(o.status); });
			if (orderList.length) {
				html += '<h6 class="pms-section">Requisitions</h6><p>' + orderList.join('<br>') + '</p>';
			}
			if (res.used_parts.length) {
				html += '<h6 class="pms-section">Parts Used</h6><ul>' + $.map(res.used_parts, function (u) { return '<li>' + esc(u.name) + ' &times; ' + esc(u.quantity) + ' ' + esc(u.unit) + '</li>'; }).join('') + '</ul>';
			}
			if (def.description) {
				html += '<h6 class="pms-section">Instructions</h6><p style="white-space:pre-line;">' + esc(def.description) + '</p>';
			}
			if (def.checklist.length) {
				html += '<h6 class="pms-section">Checklist</h6><ol class="pms-jd-checks">' +
					$.map(def.checklist, function (c) { return '<li>' + esc(c.text) + '</li>'; }).join('') + '</ol>';
			}
			$('#pj-details').html(html);

			// History: earlier jobs of this same definition.
			$('#pj-history-count').text(res.history.length ? '(' + res.history.length + ')' : '');
			$('#pj-history').html($.map(res.history, function (h) {
				var lines = [];
				if (h.status === 'Cancelled') {
					lines.push('Cancelled &middot; was due ' + fmtDate(h.due_date));
				} else {
					lines.push('<strong>' + fmtDate(h.completed_at) + '</strong>' + (h.done_by ? ' &middot; ' + esc(h.done_by) : '') +
						(h.rh_at_completion !== null ? ' &middot; ' + fmtRh(h.rh_at_completion) + ' h' : '') +
						(h.condition_after ? ' &middot; ' + esc(h.condition_after) : '') + ' &middot; due ' + fmtDate(h.due_date));
				}
				if (h.remark) { lines.push(esc(h.remark)); }
				if (h.overdue_reason) { lines.push('<span class="text-danger">Late: ' + esc(h.overdue_reason) + (h.overdue_remark ? ' — ' + esc(h.overdue_remark) : '') + '</span>'); }
				if (h.reschedule_reason) { lines.push('<span class="pms-muted">Rescheduled from ' + fmtDate(h.original_due_date) + ': ' + esc(h.reschedule_reason) + '</span>'); }
				var who = [];
				if (h.completed_by) { who.push('entered by ' + esc(h.completed_by)); }
				if (h.approved_by) { who.push('approved by ' + esc(h.approved_by) + ' ' + esc(h.approved_at)); }
				if (who.length) { lines.push('<span class="pms-muted">' + who.join(' &middot; ') + '</span>'); }
				return '<div class="pms-history-item ' + h.status + '">' + status(h.status) + ' ' + lines.join('<br>') + '</div>';
			}).join('') || '<p class="pms-muted">This job hasn\'t been done before.</p>');

			renderJobParts(res);
			$('#pj-used-list').empty();
			$('#pj-open-complete').toggleClass('pms-hidden', !res.can.complete);
			$('#pj-open-reschedule').toggleClass('pms-hidden', !res.can.reschedule);
			$('#pj-approve').toggleClass('pms-hidden', !res.can.approve);
			$('#pj-edit-def').toggleClass('pms-hidden', !res.can.edit_definition || typeof PmsJobDef === 'undefined');
			$('#pj-complete-form, #pj-reschedule-form').addClass('pms-hidden');
			$modal.find('.nav-tabs a[href="#pj-tab-details"]').tab('show');
			$modal.modal('show');
		}).fail(function (xhr) { toastr.error(errorText(xhr)); });
	}

	function renderJobParts(res) {
		var parts = res.parts, can = res.can;
		$('#pj-parts-count').text(parts.length ? '(' + parts.length + ')' : '');
		$('#pj-parts').html($.map(parts, function (p) {
			var codes = [p.article_no, p.part_no, p.drawing_no].filter(Boolean).join(' · ');
			var free = !p.order, pickable = free && p.item_id;
			var req = p.order
				? '<a class="pp-req" href="' + esc(p.order.url) + '">' + esc(p.order.req_no || 'Draft') + '</a><span class="pp-sub">' + esc(p.order.status) + '</span>'
				: '<span class="pms-muted">Not requisitioned</span>';
			var unlink = can.requisition && p.order && p.order.draft
				? '<button type="button" class="btn btn-sm btn-outline-secondary pj-part-unreq" data-id="' + p.id + '" title="Remove from the draft requisition"><i class="fas fa-times-circle"></i> Remove from requisition</button>' : '';
			var buttons = unlink + (can.parts_edit && free
				? '<button type="button" class="btn btn-sm btn-info pj-part-edit" data-id="' + p.id + '" title="Edit"><i class="fas fa-edit"></i></button> ' +
				  '<button type="button" class="btn btn-sm btn-danger pj-part-delete" data-id="' + p.id + '" title="Remove"><i class="fas fa-trash-alt"></i></button>' : '');
			return '<tr><td>' + (can.requisition ? '<input type="checkbox" class="pj-part-pick" value="' + p.id + '"' + (pickable ? '' : ' disabled title="' +
					(free ? 'Not in the item catalogue, so it can\'t be requisitioned' : 'Already on a requisition') + '"') + '>' : '') + '</td>' +
				'<td>' + esc(p.part_name) + (p.item_id ? '' : ' <span class="pms-flag pms-flag-inactive">custom</span>') + (codes ? '<span class="pp-sub">' + esc(codes) + '</span>' : '') + '</td>' +
				'<td class="pms-num">' + esc(p.quantity) + ' ' + esc(p.unit) + '</td>' +
				'<td class="pms-num">' + (p.est_price !== null ? fmtRh(p.est_price).replace(/\.0$/, '') : '—') + (p.est_delivery_weeks !== null ? '<span class="pp-sub">' + esc(p.est_delivery_weeks) + ' wk</span>' : '') + '</td>' +
				'<td>' + req + '</td><td class="action">' + buttons + '</td></tr>';
		}).join('') || '<tr><td colspan="6" class="text-center pms-muted">No parts listed for this job.</td></tr>');
		$('#pj-add-part').toggleClass('pms-hidden', !can.parts_edit);
		$('#pj-create-req').toggleClass('pms-hidden', !can.requisition);
		$('#pj-req-note').toggleClass('pms-hidden', !can.requisition);
	}

	function reloadParts() {
		return $.getJSON(jobsUrl + '/' + current.job.id).done(function (res) { current = res; renderJobParts(res); });
	}

	$('#pj-add-part').on('click', function () {
		PmsPartForm.open(null, function (values) {
			return $.post(jobsUrl + '/' + current.job.id + '/parts', $.extend({ _token: csrf }, values)).done(function () { toastr.success('Part added.'); reloadParts(); });
		});
	});
	$('#pj-parts').on('click', '.pj-part-edit', function () {
		var id = $(this).data('id');
		var part = $.grep(current.parts, function (p) { return p.id === id; })[0];
		PmsPartForm.open(part, function (values) {
			return $.post('{{ url('/pms/parts') }}/' + id + '/update', $.extend({ _token: csrf }, values)).done(function () { toastr.success('Part updated.'); reloadParts(); });
		});
	});
	$('#pj-parts').on('click', '.pj-part-delete', function () {
		var id = $(this).data('id');
		$.post('{{ url('/pms/parts') }}/' + id + '/delete', { _token: csrf })
			.done(function () { toastr.success('Part removed.'); reloadParts(); })
			.fail(function (xhr) { toastr.error(errorText(xhr)); });
	});
	$('#pj-parts').on('click', '.pj-part-unreq', function () {
		var id = $(this).data('id');
		$.post('{{ url('/pms/parts') }}/' + id + '/remove-from-requisition', { _token: csrf })
			.done(function () { toastr.success('Part taken off the requisition.'); reloadParts(); })
			.fail(function (xhr) { toastr.error(errorText(xhr)); });
	});
	$('#pj-create-req').on('click', function () {
		var ids = $('#pj-parts .pj-part-pick:checked').map(function () { return this.value; }).get();
		if (!ids.length) { toastr.error('Tick the parts to put on the requisition.'); return; }
		var $btn = $(this).prop('disabled', true);
		$.post(jobsUrl + '/' + current.job.id + '/requisition', { _token: csrf, part_ids: ids })
			.done(function (res) { toastr.success(res.message); window.location.href = res.redirect; })
			.fail(function (xhr) { toastr.error(errorText(xhr)); $btn.prop('disabled', false); });
	});

	// ---- Parts used (on completion) ------------------------------------------
	var usedTimer = null;
	function renumberUsed() {
		$('#pj-used-list .pp-used-row').each(function (i) {
			$(this).find('.u-item').attr('name', 'used_parts[' + i + '][item_id]');
			$(this).find('.u-qty').attr('name', 'used_parts[' + i + '][quantity]');
		});
	}
	$('#pj-used-search').on('input', function () {
		var q = $.trim($(this).val());
		clearTimeout(usedTimer);
		usedTimer = setTimeout(function () {
			$.getJSON(jobsUrl + '/' + current.job.id + '/stock-items', { q: q }).done(function (items) {
				$('#pj-used-results').removeClass('pms-hidden').html($.map(items, function (it, i) {
					return '<button type="button" class="pp-result" data-i="' + i + '"><strong>' + esc(it.name) + '</strong> <span class="pms-muted">' +
						esc(it.article_no || '') + ' · ' + it.stock_qty + ' ' + esc(it.unit || '') + ' on board</span></button>';
				}).join('') || '<div class="pp-none pms-muted">Nothing in stock matches.</div>').data('items', items);
			});
		}, 250);
	}).on('focus', function () { if (!$('#pj-used-results').children().length) { $(this).trigger('input'); } });
	$('#pj-used-results').on('click', '.pp-result', function () {
		var it = $('#pj-used-results').data('items')[$(this).data('i')];
		$('#pj-used-results').addClass('pms-hidden').empty();
		$('#pj-used-search').val('');
		if ($('#pj-used-list .u-item[value="' + it.id + '"]').length) { toastr.info(it.name + ' is already in the list.'); return; }
		$('#pj-used-list').append('<div class="pp-used-row" data-stock="' + it.stock_qty + '" data-name="' + esc(it.name) + '">' +
			'<input type="hidden" class="u-item" value="' + it.id + '">' +
			'<span class="pp-used-name">' + esc(it.name) + ' <span class="pms-muted">(' + it.stock_qty + ' ' + esc(it.unit || '') + ' on board)</span></span>' +
			'<input type="number" class="form-control form-control-sm u-qty" min="1" max="' + it.stock_qty + '" step="1" value="1" aria-label="Quantity used">' +
			'<button type="button" class="btn btn-sm btn-outline-danger u-remove" title="Remove"><i class="fas fa-trash-alt"></i></button></div>');
		renumberUsed();
	});
	$('#pj-used-list').on('click', '.u-remove', function () { $(this).closest('.pp-used-row').remove(); renumberUsed(); });
	$(document).on('click', function (e) { if (!$(e.target).closest('#pj-used-group').length) { $('#pj-used-results').addClass('pms-hidden'); } });

	function showPanel(which) {
		$('#pj-complete-form').toggleClass('pms-hidden', which !== 'complete');
		$('#pj-reschedule-form').toggleClass('pms-hidden', which !== 'reschedule');
		$modal.find('.nav-tabs a[href="#pj-tab-details"]').tab('show');
		if (which) {
			setTimeout(function () {
				var el = document.getElementById(which === 'complete' ? 'pj-complete-form' : 'pj-reschedule-form');
				el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}, 50);
		}
	}
	$modal.on('click', '.pj-cancel', function () { showPanel(null); });

	// Completion: shows the late-reason fields as soon as the date or RH
	// entered is past due - the scheduler makes the same check on save.
	function syncLate() {
		var job = current.job;
		var date = $('#pj-completed-at').val(), rh = parseFloat($('#pj-rh').val());
		var late = (job.due_date && date && date > job.due_date) ||
			(current.definition.uses_rh && job.due_running_hours !== null && !isNaN(rh) && rh > job.due_running_hours);
		$('#pj-late-group').toggleClass('pms-hidden', !late);
	}

	$('#pj-open-complete').on('click', function () {
		var $f = $('#pj-complete-form');
		$f[0].reset();
		$('#pj-completed-at').val(today).attr('max', today);
		$('#pj-done-by').val(current.definition.rank_id || '');
		$('#pj-condition').val(current.component.condition_id || defaultConditionId);
		$('#pj-rh-group').toggleClass('pms-hidden', !current.definition.uses_rh);
		$('#pj-rh').val(current.definition.uses_rh && current.component.current_rh !== null ? current.component.current_rh : '');
		$('#pj-checklist').html($.map(current.definition.checklist, function (c, i) {
			return '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="pj-check-' + i + '" name="checklist[]" value="' + c.id + '">' +
				'<label class="custom-control-label" for="pj-check-' + i + '">' + esc(c.text) + '</label></div>';
		}).join(''));
		$('#pj-checklist-group').toggleClass('pms-hidden', !current.definition.checklist.length);
		syncLate();
		showPanel('complete');
	});
	$('#pj-completed-at, #pj-rh').on('input change', syncLate);

	$('#pj-complete-form').on('submit', function (e) {
		e.preventDefault();
		var $f = $(this);
		if ($f.find('[name="checklist[]"]:not(:checked)').length) {
			toastr.error('Tick every checklist item before completing the job.');
			return;
		}
		var tooMany = null;
		$f.find('.pp-used-row').each(function () {
			if (parseInt($(this).find('.u-qty').val(), 10) > parseInt($(this).attr('data-stock'), 10)) { tooMany = $(this).attr('data-name'); }
		});
		if (tooMany) { toastr.error('You can\'t use more ' + tooMany + ' than is in stock.'); return; }
		var payload = $f.find(':input:visible, [name="checklist[]"], [name^="used_parts"]').serializeArray();
		payload.push({ name: '_token', value: csrf });
		var $btn = $f.find('[type=submit]').prop('disabled', true);
		$.post(jobsUrl + '/' + current.job.id + '/complete', $.param(payload))
			.done(function (res) { afterAction(res.message); })
			.fail(function (xhr) { toastr.error(errorText(xhr)); })
			.always(function () { $btn.prop('disabled', false); });
	});

	$('#pj-open-reschedule').on('click', function () {
		$('#pj-reschedule-form')[0].reset();
		$('#pj-new-due').attr('min', tomorrow).val(current.job.due_date && current.job.due_date > today ? current.job.due_date : '');
		var hasRh = current.job.due_running_hours !== null;
		$('#pj-new-rh-group').toggleClass('pms-hidden', !hasRh);
		$('#pj-new-rh-hint').text(hasRh ? 'Currently ' + fmtRh(current.job.due_running_hours) + ' h. Leave blank to add the average running hours for the days added.' : '');
		showPanel('reschedule');
	});

	$('#pj-reschedule-form').on('submit', function (e) {
		e.preventDefault();
		var $btn = $(this).find('[type=submit]').prop('disabled', true);
		$.post(jobsUrl + '/' + current.job.id + '/reschedule', $(this).serialize() + '&_token=' + encodeURIComponent(csrf))
			.done(function (res) { afterAction(res.message); })
			.fail(function (xhr) { toastr.error(errorText(xhr)); })
			.always(function () { $btn.prop('disabled', false); });
	});

	$('#pj-approve').on('click', function () {
		var job = current.job;
		swal({
			title: 'Approve this job?',
			text: current.definition.name + ' — completed ' + fmtDate(job.completed_at) + (job.done_by ? ' by ' + job.done_by : '') + '.',
			type: 'question',
			showCancelButton: true,
			confirmButtonText: 'Approve',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }
			$.post(jobsUrl + '/' + job.id + '/approve', { _token: csrf })
				.done(function (res) { afterAction(res.message); })
				.fail(function (xhr) { toastr.error(errorText(xhr)); });
		});
	});

	$('#pj-edit-def').on('click', function () {
		var definitionId = current.definition.id;
		$modal.one('hidden.bs.modal', function () { PmsJobDef.edit(definitionId); });
		$modal.modal('hide');
	});
	if (window.PmsJobDef) {
		PmsJobDef.onSaved = function () { load(); };
	}

	function afterAction(message) {
		toastr.success(message);
		$modal.modal('hide');
		load();
	}

	// A link from the Required Parts page: open that job's dialog once the list is in.
	var openJobId = new URLSearchParams(window.location.search).get('open');
	if (openJobId) { $(document).one('pms-jobs-loaded', function () { openJob(openJobId); }); }

	// Arrived from a component's job count in the tree: start scoped to it.
	if (state.component) { selectComponent(state.component); }
	load();
});
</script>
@endsection
