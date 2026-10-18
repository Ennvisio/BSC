{{-- window.PmsPartForm.open(part|null, onSave): the part form modal.
	 onSave(values) gets the form's values and returns a jqXHR/promise to wait
	 on (an AJAX save) or nothing (the caller keeps it locally); the modal
	 closes when that succeeds. --}}
<script>
window.PmsPartForm = (function () {
	var $modal = $('#pms-part-modal'), $form = $('#pms-part-form');
	var searchUrl = '{{ route('pms.parts.search-items') }}';
	var onSave = null, timer = null;
	var catalogueFields = ['part_name', 'article_no', 'part_no', 'drawing_no', 'unit'];
	var api = {};

	function esc(text) { return $('<div>').text(text == null ? '' : String(text)).html(); }

	function setLinked(linked) {
		$('#pp-linked').toggleClass('pms-hidden', !linked);
		$.each(catalogueFields, function (_, f) { $form.find('[name="' + f + '"]').prop('readonly', linked); });
		$('#pp-maker').prop('disabled', linked && !!$('#pp-maker').val());
	}

	function fill(part) {
		$form[0].reset();
		$('#pp-item-id').val('');
		$('#pp-results').addClass('pms-hidden').empty();
		$.each(part || {}, function (name, value) {
			if (name === 'item_id') { $('#pp-item-id').val(value || ''); return; }
			$form.find('[name="' + name + '"]').val(value === null || value === undefined ? '' : value);
		});
		setLinked(!!(part && part.item_id));
	}

	api.open = function (part, callback) {
		onSave = callback;
		$('#pp-title').text(part ? 'Edit Part' : 'Add Part');
		fill(part);
		$modal.modal('show');
	};

	$('#pp-search').on('input', function () {
		var q = $.trim($(this).val());
		clearTimeout(timer);
		if (q.length < 2) { $('#pp-results').addClass('pms-hidden').empty(); return; }
		timer = setTimeout(function () {
			$.getJSON(searchUrl, { q: q }).done(function (items) {
				$('#pp-results').removeClass('pms-hidden').html($.map(items, function (it, i) {
					return '<button type="button" class="pp-result" data-i="' + i + '"><strong>' + esc(it.part_name) + '</strong>' +
						'<span class="pms-muted"> ' + esc([it.article_no, it.part_no, it.drawing_no].filter(Boolean).join(' · ')) + '</span></button>';
				}).join('') || '<div class="pp-none pms-muted">No catalogue item matches.</div>').data('items', items);
			});
		}, 250);
	});

	$('#pp-results').on('click', '.pp-result', function () {
		var it = $('#pp-results').data('items')[$(this).data('i')];
		$.each(it, function (name, value) {
			if (name === 'item_id') { $('#pp-item-id').val(value); return; }
			$form.find('[name="' + name + '"]').val(value === null ? '' : value);
		});
		$('#pp-maker').prop('disabled', false).val(it.manufacturer_id || '');
		setLinked(true);
		$('#pp-search').val('');
		$('#pp-results').addClass('pms-hidden').empty();
		$('#pp-qty').trigger('focus');
	});

	$('#pp-unlink').on('click', function () { $('#pp-item-id').val(''); setLinked(false); });

	$form.on('submit', function (e) {
		e.preventDefault();
		var values = {};
		// A disabled maker select still counts: it was filled from the catalogue.
		$.each($form.serializeArray(), function (_, f) { values[f.name] = f.value; });
		values.manufacturer_id = $('#pp-maker').val();
		if (!values.item_id && !$.trim(values.part_name)) { toastr.error('Enter the part name, or pick a catalogue item.'); return; }
		if (!values.item_id && !$.trim(values.unit)) { toastr.error('Enter the unit.'); return; }
		if (!(parseInt(values.quantity, 10) >= 1)) { toastr.error('Enter a quantity of at least 1.'); return; }

		var $btn = $form.find('[type=submit]').prop('disabled', true);
		var result = onSave(values);
		if (result && result.done) {
			result.done(function () { $modal.modal('hide'); })
				.fail(function (xhr) {
					var errors = xhr.responseJSON && xhr.responseJSON.errors;
					toastr.error(errors ? Object.values(errors)[0][0] : ((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.'));
				})
				.always(function () { $btn.prop('disabled', false); });
		} else {
			$btn.prop('disabled', false);
			$modal.modal('hide');
		}
	});

	// A second modal opened over another one: sit above it, and give the first
	// one back its scrolling when this one closes (Bootstrap 4 doesn't).
	$modal.on('shown.bs.modal', function () {
		$modal.css('z-index', 1065);
		$('.modal-backdrop').last().css('z-index', 1060);
	}).on('hidden.bs.modal', function () {
		if ($('.modal.show').length) { $('body').addClass('modal-open'); }
	});

	return api;
})();
</script>
