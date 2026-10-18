{{-- window.PmsJobDef: open the job definition modal for a new job on a
	 component (add) or an existing definition (edit). After a save it calls
	 PmsJobDef.onSaved (default: reload the page). --}}
<script>
window.PmsJobDef = (function () {
	var $modal = $('#pms-jd-modal'), $form = $('#pms-jd-form');
	var showUrl = '{{ url('/pms/job-definitions') }}';
	var storeUrl = '{{ url('/pms/components') }}';
	var periods = [['Day', 'Days'], ['Week', 'Weeks'], ['Month', 'Months'], ['Year', 'Years'], ['RunningHour', 'Running hours']];
	var statusLabels = { Planned: 'Planned', DueSoon: 'Due Soon', Overdue: 'Overdue', WaitingApproval: 'Waiting Approval', Completed: 'Completed', Cancelled: 'Cancelled' };
	var hasCounter = false, saveUrl = null, canEditParts = true;
	var parts = [];
	var api = { onSaved: function () { window.location.reload(); } };

	function esc(text) { return $('<div>').text(text == null ? '' : String(text)).html(); }

	function renderParts() {
		$('#jd-parts-count').text(parts.length ? '(' + parts.length + ')' : '');
		$('#jd-parts').html($.map(parts, function (p, i) {
			var codes = [p.article_no, p.part_no, p.drawing_no].filter(Boolean).join(' · ');
			return '<tr><td>' + esc(p.part_name || '') + (p.item_id ? '' : ' <span class="pms-flag pms-flag-inactive" title="Not in the catalogue - can\'t be requisitioned">custom</span>') +
				(codes ? '<span class="pp-sub">' + esc(codes) + '</span>' : '') + '</td>' +
				'<td class="pms-num">' + esc(p.quantity) + '</td><td>' + esc(p.unit || '') + '</td>' +
				'<td class="pms-num">' + (p.est_price === null || p.est_price === '' || p.est_price === undefined ? '—' : esc(p.est_price)) + '</td>' +
				'<td class="pms-num">' + (p.est_delivery_weeks === null || p.est_delivery_weeks === '' || p.est_delivery_weeks === undefined ? '—' : esc(p.est_delivery_weeks) + ' wk') + '</td>' +
				'<td class="action">' + (canEditParts ? '<button type="button" class="btn btn-sm btn-info jd-part-edit" data-i="' + i + '" title="Edit"><i class="fas fa-edit"></i></button> ' +
				'<button type="button" class="btn btn-sm btn-danger jd-part-remove" data-i="' + i + '" title="Remove"><i class="fas fa-trash-alt"></i></button>' : '') + '</td></tr>';
		}).join('') || '<tr><td colspan="6" class="text-center pms-muted">No parts yet.</td></tr>');
	}

	function addInterval(every, period) {
		var i = $('#jd-intervals .jd-interval').length;
		var $row = $('<div class="jd-interval">' +
			'<span class="pms-muted">Every</span>' +
			'<input type="number" class="form-control" min="1" step="1" required aria-label="Every">' +
			'<select class="form-control" aria-label="Period"></select>' +
			'<button type="button" class="btn btn-sm btn-outline-danger jd-remove" title="Remove interval"><i class="fas fa-trash-alt"></i></button>' +
			'</div>');
		var $select = $row.find('select');
		$.each(periods, function (_, p) {
			if (p[0] === 'RunningHour' && !hasCounter) { return; }
			$select.append($('<option>').val(p[0]).text(p[1]));
		});
		$row.find('input').val(every || '');
		$select.val(period || 'Month');
		$('#jd-intervals').append($row);
		renumber();
	}

	function addCheck(text) {
		var $li = $('<li class="jd-check"><input type="text" class="form-control" name="checklist[]" maxlength="255" aria-label="Checklist item">' +
			'<button type="button" class="btn btn-sm btn-outline-danger jd-remove" title="Remove item"><i class="fas fa-trash-alt"></i></button></li>');
		$li.find('input').val(text || '');
		$('#jd-checklist').append($li);
	}

	// Interval inputs are named by position, so re-index after add/remove.
	function renumber() {
		$('#jd-intervals .jd-interval').each(function (i) {
			$(this).find('input').attr('name', 'intervals[' + i + '][every]');
			$(this).find('select').attr('name', 'intervals[' + i + '][period]');
		});
	}

	function setKind(single) {
		$('#jd-is-single').val(single ? 1 : 0);
		$('#jd-kind-single').prop('checked', single);
		$('#jd-kind-recurring').prop('checked', !single);
		$('#jd-recurring').toggleClass('pms-hidden', single);
		$('#jd-single').toggleClass('pms-hidden', !single);
		syncDueType();
	}

	function syncDueType() {
		var rh = $('#jd-due-type').val() === 'RunningHours';
		$('#jd-due-date-group').toggleClass('pms-hidden', rh);
		$('#jd-due-rh-group').toggleClass('pms-hidden', !rh);
	}

	function reset(component) {
		$form[0].reset();
		$('#jd-intervals, #jd-checklist, #jd-jobs').empty();
		parts = []; canEditParts = true; renderParts();
		$('#pms-jd-modal .nav-tabs a[href="#jd-tab-job"]').tab('show');
		$('#jd-jobs-wrap').addClass('pms-hidden');
		$form.find(':input').prop('disabled', false);
		$('#jd-save, #jd-add-interval, #jd-add-check, #jd-add-part').show();
		hasCounter = !!component.has_counter;
		// Running hours only make sense on a component with a meter.
		$form.find('.jd-rh-only').prop('hidden', !hasCounter).prop('disabled', !hasCounter);
		$('#pms-jd-component').html('<i class="fas fa-cog"></i> ' + esc(component.name) +
			(hasCounter ? '' : ' <span class="pms-muted">&middot; no running-hour counter, so calendar intervals only</span>'));
		$('#jd-save').prop('disabled', false);
	}

	function fill(def) {
		$.each(def, function (name, value) {
			var $input = $form.find('[name="' + name + '"]');
			if (!$input.length || name === 'is_single') { return; }
			if ($input.is(':checkbox')) { $input.prop('checked', !!value); }
			else { $input.val(value === null ? '' : value); }
		});
	}

	api.add = function (component) {
		reset(component);
		saveUrl = storeUrl + '/' + component.id + '/job-definitions';
		$('#pms-jd-title').text('Add Job');
		$('#jd-active-wrap').addClass('pms-hidden');
		$('#jd-active').prop('checked', true);
		// A new job on a critical / class item usually is one too.
		$('#jd-critical').prop('checked', !!component.is_critical);
		$('#jd-class').prop('checked', !!component.is_class_relevant);
		setKind(false);
		addInterval('', 'Month');
		addCheck('');
		$modal.modal('show');
		setTimeout(function () { $('#jd-name').trigger('focus'); }, 300);
	};

	api.edit = function (definitionId) {
		$.getJSON(showUrl + '/' + definitionId).done(function (res) {
			var def = res.definition;
			reset(res.component);
			saveUrl = showUrl + '/' + def.id + '/update';
			$('#pms-jd-title').text(res.can_edit ? 'Edit Job' : 'Job Definition');
			$('#jd-active-wrap').removeClass('pms-hidden');
			fill(def);
			setKind(!!def.is_single);
			$.each(def.intervals, function (_, iv) { addInterval(iv.every, iv.period); });
			if (!def.is_single && !def.intervals.length) { addInterval('', 'Month'); }
			parts = def.parts || [];
			canEditParts = !!res.can_edit;
			renderParts();
			$.each(def.checklist, function (_, text) { addCheck(text); });
			if (!def.checklist.length) { addCheck(''); }

			$('#jd-jobs').html($.map(res.jobs, function (j) {
				return '<tr><td><span class="pms-status pms-status-' + j.status + '">' + esc(statusLabels[j.status] || j.status) + '</span></td>' +
					'<td>' + esc(j.due_date || '—') + '</td><td>' + esc(j.original_due_date || '—') + '</td>' +
					'<td class="pms-num">' + (j.due_running_hours === null ? '—' : j.due_running_hours.toFixed(1)) + '</td>' +
					'<td>' + esc(j.completed_at || '—') + '</td><td>' + esc(j.done_by || '—') + '</td></tr>';
			}).join('') || '<tr><td colspan="6" class="text-center pms-muted">No jobs generated yet.</td></tr>');
			$('#jd-jobs-wrap').removeClass('pms-hidden');

			$form.find(':input').not('[data-dismiss]').prop('disabled', !res.can_edit);
			$form.find('.jd-rh-only').prop('disabled', !res.can_edit || !hasCounter);
			$('#jd-save, #jd-add-interval, #jd-add-check, #jd-add-part').toggle(res.can_edit);
			$modal.modal('show');
		}).fail(function (xhr) {
			toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load the job.');
		});
	};

	$('input[name="jd_kind"]').on('change', function () { setKind($('#jd-kind-single').is(':checked')); });
	$('#jd-due-type').on('change', syncDueType);
	$('#jd-add-interval').on('click', function () { addInterval('', 'Month'); });
	$('#jd-add-check').on('click', function () { addCheck(''); $('#jd-checklist input').last().trigger('focus'); });
	$form.on('click', '.jd-remove', function () {
		$(this).closest('.jd-interval, .jd-check').remove();
		renumber();
	});

	$('#jd-add-part').on('click', function () {
		PmsPartForm.open(null, function (values) { parts.push(values); renderParts(); });
	});
	$form.on('click', '.jd-part-edit', function () {
		var i = $(this).data('i');
		PmsPartForm.open(parts[i], function (values) { parts[i] = values; renderParts(); });
	});
	$form.on('click', '.jd-part-remove', function () {
		parts.splice($(this).data('i'), 1);
		renderParts();
	});

	$form.on('submit', function (e) {
		e.preventDefault();
		var $btn = $('#jd-save').prop('disabled', true);
		// The hidden side of the form (single vs recurring) is stripped server-side.
		var single = $('#jd-is-single').val() === '1';
		var $fields = $form.find(':input').not('[name="jd_kind"]');
		if (single) { $fields = $fields.not('#jd-intervals :input, #jd-start'); }
		var payload = $fields.serialize() + '&' + $.param({ parts: parts });
		$.post(saveUrl, payload)
			.done(function (res) {
				$modal.modal('hide');
				toastr.success(res.message || 'Saved.');
				api.onSaved(res);
			})
			.fail(function (xhr) {
				$btn.prop('disabled', false);
				var errors = xhr.responseJSON && xhr.responseJSON.errors;
				toastr.error(errors ? Object.values(errors)[0][0]
					: ((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.'));
			});
	});

	return api;
})();
</script>
