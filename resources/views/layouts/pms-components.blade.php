@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">PMS Components &mdash; {{ $vessel->name }}</strong>
			@if($canEdit)
			<div class="right-buttons">
				<a href="{{ route('pms.components.import', ['vessel_id' => $vessel->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-upload"></i> Import</a>
				<button type="button" class="btn btn-sm btn-primary" id="pms-add-root"><i class="fas fa-plus-square"></i> Add Component</button>
			</div>
			@endif
		</div>
		<div class="card-body">
			@if(session('message'))
			<div class="alert alert-success">{{ session('message') }}</div>
			@endif
			<div class="pms-toolbar">
				{{-- Ship users are pinned to their own vessel (PmsAccess::pmsVessel),
					 so only shore users get a vessel picker at all. --}}
				@if($vessels->isNotEmpty())
				<form method="GET" action="{{ route('pms.components.index') }}">
					<select name="vessel_id" class="form-control" id="pms-vessel-picker" aria-label="Vessel">
						@foreach($vessels as $v)
						<option value="{{ $v->id }}" {{ $v->id === $vessel->id ? 'selected' : '' }}>{{ $v->name }}</option>
						@endforeach
					</select>
				</form>
				@endif
				<input type="search" class="form-control pms-search" id="pms-search" placeholder="Search name, model, serial, maker…" aria-label="Search components">
				<span class="spacer"></span>
				<span class="pms-muted" style="font-size:13px;">{{ $total }} {{ \Illuminate\Support\Str::plural('component', $total) }}</span>
				<button type="button" class="btn btn-sm btn-outline-secondary" id="pms-expand-all">Expand all</button>
				<button type="button" class="btn btn-sm btn-outline-secondary" id="pms-collapse-all">Collapse all</button>
			</div>

			@if(!$canEdit)
			<p class="pms-note"><i class="fas fa-eye"></i> View only &mdash; this vessel's components are maintained on board.</p>
			@endif

			@forelse($locations as $location)
			@php $roots = $rootsByLocation->get($location->id, collect()); @endphp
			<section class="pms-location" data-location="{{ $location->id }}">
				<div class="pms-location-head" role="button" tabindex="0">
					<i class="fas fa-chevron-down caret"></i>
					<h3>{{ $location->name }}</h3>
					<span class="pms-loc-count">{{ $roots->count() }} {{ \Illuminate\Support\Str::plural('system', $roots->count()) }}</span>
				</div>
				<div class="pms-location-body">
					@if($roots->isEmpty())
					<div class="pms-location-empty">No components in {{ $location->name }} yet.</div>
					@else
					<ul class="pms-tree">
						@foreach($roots as $node)
						@include('partials.pms-component-node', ['node' => $node])
						@endforeach
					</ul>
					@endif
				</div>
			</section>
			@empty
			<p class="pms-note">No PMS locations are set up yet &mdash; a super-admin adds them under PMS &rsaquo; Master Data.</p>
			@endforelse
			<p class="pms-location-empty pms-hidden" id="pms-no-match">No components match your search.</p>
		</div>
	</div>
</div>

@if($canEdit)
<div class="modal fade" id="pms-component-modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="pms-component-form" autocomplete="off">
				@csrf
				<input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title text-center"><i class="fas fa-cogs"></i> &nbsp; <span id="pms-component-title">Add Component</span></legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<div class="pms-form-grid">
						<div class="form-group">
							<label for="pc-location">Location <span class="text-danger">*</span></label>
							<select class="form-control" id="pc-location" name="location_id" required>
								@foreach($locations as $location)
								<option value="{{ $location->id }}">{{ $location->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="pc-parent">Parent</label>
							{{-- Every option carries its location; the JS hides the ones
								 outside the chosen location, since a child always sits in
								 its parent's location. --}}
							<select class="form-control" id="pc-parent" name="parent_id">
								<option value="">— None (top-level system) —</option>
								@foreach($parentOptions as $option)
								<option value="{{ $option['id'] }}" data-location="{{ $option['location_id'] }}">{{ str_repeat('— ', $option['depth']) }}{{ $option['name'] }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group full">
							<label for="pc-name">Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="pc-name" name="name" maxlength="255" required>
						</div>
						<div class="form-group">
							<label for="pc-type">Type / Model</label>
							<input type="text" class="form-control" id="pc-type" name="type" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pc-category">Category <span class="text-danger">*</span></label>
							{{-- Starts empty on purpose: preselecting the first category
								 silently filed every component under whatever sorts first. --}}
							<select class="form-control" id="pc-category" name="category_id" required>
								<option value="" disabled selected>Select…</option>
								@foreach($categories as $category)
								<option value="{{ $category->id }}" data-critical="{{ $category->is_critical ? 1 : 0 }}" data-class="{{ $category->is_class_relevant ? 1 : 0 }}">{{ $category->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="pc-manufacturer">Manufacturer</label>
							<select class="form-control" id="pc-manufacturer" name="manufacturer_id">
								<option value="">—</option>
								@foreach($manufacturers as $m)
								<option value="{{ $m->id }}">{{ $m->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="pc-condition">Condition <span class="text-danger">*</span></label>
							<select class="form-control" id="pc-condition" name="condition_id" required>
								@foreach($conditions as $condition)
								<option value="{{ $condition->id }}">{{ $condition->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="pc-serial">Serial No.</label>
							<input type="text" class="form-control" id="pc-serial" name="serial_no" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pc-part">Part No.</label>
							<input type="text" class="form-control" id="pc-part" name="part_no" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pc-class-code">Class Code</label>
							<input type="text" class="form-control" id="pc-class-code" name="class_code" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pc-internal-code">Internal Code</label>
							<input type="text" class="form-control" id="pc-internal-code" name="internal_code" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pc-installed">Installation Date</label>
							<input type="date" class="form-control" id="pc-installed" name="installation_date" max="{{ now()->toDateString() }}">
						</div>
						<div class="form-group">
							<label for="pc-sort">Sort Order</label>
							<input type="number" class="form-control" id="pc-sort" name="sort_order" min="0" step="1" value="0">
						</div>
						<div class="form-group full">
							<div class="pms-checks">
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="pc-counter" name="has_counter" value="1">
									<label class="custom-control-label" for="pc-counter">Has running-hour counter</label>
								</div>
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="pc-critical" name="is_critical" value="1">
									<label class="custom-control-label" for="pc-critical">Critical</label>
								</div>
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="pc-class" name="is_class_relevant" value="1">
									<label class="custom-control-label" for="pc-class">Class relevant</label>
								</div>
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="pc-inactive" name="is_inactive" value="1">
									<label class="custom-control-label" for="pc-inactive">Inactive</label>
								</div>
							</div>
						</div>
						<div class="form-group full">
							<label for="pc-remark">Remark</label>
							<textarea class="form-control" id="pc-remark" name="remark" rows="3" maxlength="5000"></textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>
@include('partials.pms-job-definition-modal')
@include('partials.pms-part-modal')
@endif
@endsection

@section('home-js')
@if($canEdit)
@include('partials.pms-part-script')
@include('partials.pms-job-definition-script')
@endif
<script>
$(function () {
	// --- Vessel picker (shore users) ---------------------------------------
	$('#pms-vessel-picker').on('change', function () { this.form.submit(); });

	// --- Expand / collapse ---------------------------------------------------
	$(document).on('click', '.pms-node-toggle:not(.leaf)', function () {
		$(this).closest('.pms-node').toggleClass('collapsed');
	});
	$(document).on('click keydown', '.pms-location-head', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
		e.preventDefault();
		$(this).closest('.pms-location').toggleClass('collapsed');
	});
	$('#pms-expand-all').on('click', function () {
		$('.pms-location, .pms-node').removeClass('collapsed');
	});
	$('#pms-collapse-all').on('click', function () {
		$('.pms-node').has('> ul').addClass('collapsed');
	});

	// --- Search ---------------------------------------------------------------
	// A match keeps its whole ancestor chain visible and expanded, so a hit
	// deep in the tree is never shown floating without its context.
	$('#pms-search').on('input', function () {
		var q = $.trim($(this).val()).toLowerCase();
		var $nodes = $('.pms-node');

		if (!q) {
			$nodes.removeClass('pms-hidden');
			$('.pms-location').removeClass('pms-hidden');
			$('#pms-no-match').addClass('pms-hidden');
			return;
		}

		$nodes.addClass('pms-hidden');
		// attr(), not data(): data() turns a numeric-looking string into a number.
		$nodes.filter(function () { return ($(this).attr('data-search') || '').indexOf(q) !== -1; }).each(function () {
			$(this).removeClass('pms-hidden collapsed')
				.parents('.pms-node').removeClass('pms-hidden collapsed');
		});
		$('.pms-location').each(function () {
			var hasHit = $(this).find('.pms-node:not(.pms-hidden)').length > 0;
			$(this).toggleClass('pms-hidden', !hasHit).removeClass('collapsed');
		});
		$('#pms-no-match').toggleClass('pms-hidden', $('.pms-node:not(.pms-hidden)').length > 0);
	});

	@if($canEdit)
	// --- Add / edit -----------------------------------------------------------
	var $form = $('#pms-component-form');
	var storeUrl = '{{ route("pms.components.store") }}';
	var baseUrl = '{{ url("/pms/components") }}';
	var defaultConditionId = @json($defaultConditionId);

	// Parent options outside the chosen location are hidden; picking a parent
	// pins the location to the parent's own, since children share it.
	function syncParentOptions(editingId) {
		var locationId = String($('#pc-location').val());
		var $parent = $('#pc-parent');
		$parent.find('option[data-location]').each(function () {
			var ok = String($(this).data('location')) === locationId && $(this).val() !== editingId;
			$(this).prop('hidden', !ok).prop('disabled', !ok);
		});
		if ($parent.find('option:selected').prop('disabled')) { $parent.val(''); }
	}

	function applyCategoryDefaults() {
		var $opt = $('#pc-category option:selected');
		$('#pc-critical').prop('checked', $opt.data('critical') == 1);
		$('#pc-class').prop('checked', $opt.data('class') == 1);
	}

	function openForm(title, url, values, editingId) {
		$form[0].reset();
		$form.data('url', url).data('editing', editingId || '');
		$('#pms-component-title').text(title);
		$('#pc-condition').val(defaultConditionId);
		$.each(values || {}, function (name, value) {
			var $input = $form.find('[name="' + name + '"]');
			if ($input.is(':checkbox')) { $input.prop('checked', !!value); }
			else { $input.val(value === null ? '' : value); }
		});
		if (!editingId) { applyCategoryDefaults(); }
		syncParentOptions(editingId || '');
		if (values && values.parent_id) { $('#pc-parent').val(values.parent_id); }
		$('#pc-location').prop('disabled', !!$('#pc-parent').val());
		$('#pms-component-modal').modal('show');
	}

	$('#pms-add-root').on('click', function () {
		openForm('Add Component', storeUrl, null, null);
	});

	$(document).on('click', '.pms-add-job', function () {
		PmsJobDef.add($(this).data('component'));
	});

	$(document).on('click', '.pms-add-child', function () {
		openForm('Add Sub-component', storeUrl, {
			location_id: $(this).data('location'),
			parent_id: $(this).data('id')
		}, null);
	});

	$(document).on('click', '.pms-edit', function () {
		var c = $(this).data('component');
		openForm('Edit Component', baseUrl + '/' + c.id + '/update', c, c.id);
	});

	$('#pc-location').on('change', function () { syncParentOptions($form.data('editing')); });
	$('#pc-parent').on('change', function () {
		var $opt = $(this).find('option:selected');
		if ($opt.data('location')) { $('#pc-location').val($opt.data('location')); }
		$('#pc-location').prop('disabled', !!$(this).val());
	});
	// Only for a new component - an existing one keeps its own flags.
	$('#pc-category').on('change', function () {
		if (!$form.data('editing')) { applyCategoryDefaults(); }
	});

	$form.on('submit', function (e) {
		e.preventDefault();
		// A disabled select isn't posted; the server derives location from
		// the parent anyway, but send it so validation sees a value.
		var $location = $('#pc-location').prop('disabled', false);
		var payload = $form.serialize();
		$location.prop('disabled', !!$('#pc-parent').val());

		var $btn = $form.find('[type=submit]').prop('disabled', true);
		$.post($form.data('url'), payload)
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				$btn.prop('disabled', false);
				var errors = xhr.responseJSON && xhr.responseJSON.errors;
				toastr.error(errors ? Object.values(errors)[0][0]
					: ((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.'));
			});
	});

	$(document).on('click', '.pms-delete', function () {
		var id = $(this).data('id');
		swal({
			title: 'Delete "' + $(this).data('name') + '"?',
			text: 'Its running-hour readings are kept with it.',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, delete it',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }
			$.post(baseUrl + '/' + id + '/delete', { _token: '{{ csrf_token() }}' })
				.done(function () { window.location.reload(); })
				.fail(function (xhr) {
					toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
				});
		});
	});
	@endif
});
</script>
@endsection
