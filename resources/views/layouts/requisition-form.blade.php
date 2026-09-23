@extends('layouts.admin-master')
@section('main-content')
@include('partials.requisition-form-styles')
@php
	// What was saved so far, so coming back to correct a draft shows the
	// officer's own answers rather than a blank form. On a brand-new
	// requisition there's nothing saved yet, so old() stands in for it -
	// otherwise a validation error would wipe nine questions of typing.
	$saved = function ($key) use ($formPart) {
		$posted = old('q.'.$key);
		if (is_array($posted)) {
			return [
				'choice' => is_string($posted['choice'] ?? null) ? $posted['choice'] : null,
				'followup' => is_string($posted['followup'] ?? null) ? $posted['followup'] : null,
				'fields' => is_array($posted['fields'] ?? null) ? $posted['fields'] : [],
			];
		}
		return $formPart ? $formPart->answerFor($key) : ['choice' => null, 'followup' => null, 'fields' => []];
	};
	$declared = old('declaration', $formPart && ! empty($formPart->declaration));
	$vessel = $order ? $order->vessel : auth()->user()->role->vessel;
@endphp
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">Justification form for &nbsp;
						<span style="color:red;">{{ $vessel->name ?? '' }}</span>
					</strong>
					<div class="right-button">Step 1 of 4 — Justification</div>
				</div>
				<div class="card-body">
					@if($errors->any())
					<div class="alert alert-danger">
						<strong>Please finish the following before continuing:</strong>
						<ul class="mb-0 mt-1">
							@foreach($errors->all() as $error)
							<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
					@endif

					<div class="rf-banner">
						<div class="rf-banner-title">SHIP'S SPARES DEMAND AND PROCUREMENT APPROVAL FORM</div>
					</div>

					<div class="rf-meta">
						<div><div class="k">Name of Vessel</div><div class="v">{{ $vessel->name ?? '' }}</div></div>
						<div><div class="k">Requisition No</div><div class="v text-muted">Assigned on submit</div></div>
						<div><div class="k">Date</div><div class="v">{{ $order ? $order->req_date : now()->toDateString() }}</div></div>
					</div>

					{{-- Same form either way: a brand-new requisition posts to step 1,
						 which creates the draft; an existing draft posts an edit. --}}
					<form method="POST" action="{{ $order ? route('requisition.form.store', $order) : route('requisition.step1.store') }}" id="part-a-form">
						@csrf

						@foreach($questions as $q)
						@php
							$a = $saved($q['key']);
							$name = 'q['.$q['key'].']';
							$hasError = $errors->has($q['key'])
								|| collect($q['fields'] ?? [])->contains(fn ($f) => $errors->has($q['key'].'.'.$f['key']));
						@endphp
						<div class="rf-q {{ $hasError ? 'has-error' : '' }}" id="q-{{ $q['key'] }}">
							<div class="rf-q-title">
								<span class="rf-q-no">{{ $q['no'] }}.</span>
								{{ $q['en'] }}
								@if($q['required'] ?? false)<span class="rf-req" title="Required">*</span>@endif
							</div>

							<div class="rf-opts">
								@foreach($q['options'] as $value => $label)
								<label class="rf-chip">
									@if($q['type'] === 'multi')
									<input type="checkbox" name="{{ $name }}[choice][]" value="{{ $value }}"
										{{ in_array($value, (array) $a['choice'], true) ? 'checked' : '' }}>
									@else
									{{-- Optional single-choice questions can be un-picked again
										 (see the click handler below) - a radio otherwise can't be
										 cleared once chosen, which would make a mis-click permanent. --}}
									<input type="radio" class="rf-toggle" name="{{ $name }}[choice]" value="{{ $value }}"
										{{ $a['choice'] === $value ? 'checked' : '' }}>
									@endif
									<span>{{ $label }}</span>
								</label>
								@endforeach
							</div>

							@if(!empty($q['fields']))
							<div class="rf-fields">
								@foreach($q['fields'] as $field)
								<div class="rf-field">
									<label for="f-{{ $q['key'] }}-{{ $field['key'] }}">
										{{ $field['label'] }}@if($field['required'] ?? false)<span class="rf-req">*</span>@endif
									</label>
									<input type="text"
										class="form-control {{ $field['type'] === 'date' ? 'date' : '' }} {{ $errors->has($q['key'].'.'.$field['key']) ? 'is-invalid' : '' }}"
										id="f-{{ $q['key'] }}-{{ $field['key'] }}"
										name="{{ $name }}[fields][{{ $field['key'] }}]"
										value="{{ $a['fields'][$field['key']] ?? '' }}"
										maxlength="{{ \App\RequisitionForm::MAX_FIELD_LENGTH }}"
										autocomplete="off"
										@if($field['type'] === 'date') placeholder="YYYY-MM-DD" @endif>
								</div>
								@endforeach
							</div>
							@endif

							@if(!empty($q['followup']))
							<div class="rf-followup">
								<span class="lbl">{{ $q['followup']['label'] }}</span>
								<div class="rf-opts">
									@foreach($q['followup']['options'] as $value => $label)
									<label class="rf-chip">
										<input type="radio" class="rf-toggle" name="{{ $name }}[followup]" value="{{ $value }}"
											{{ $a['followup'] === $value ? 'checked' : '' }}>
										<span>{{ $label }}</span>
									</label>
									@endforeach
								</div>
							</div>
							@endif
						</div>
						@endforeach

						{{-- The paper form's own SHIP DECLARATION block was cut off in the
							 copy this was built from, so the wording below is a placeholder
							 to be replaced with the real text. --}}
						<div class="rf-q rf-declaration {{ $errors->has('declaration') ? 'has-error' : '' }}">
							<div class="rf-q-title">Ship declaration <span class="rf-req" title="Required">*</span></div>
							<div class="custom-control custom-checkbox">
								<input type="checkbox" class="custom-control-input" id="declaration" name="declaration" value="1" {{ $declared ? 'checked' : '' }}>
								<label class="custom-control-label" for="declaration">
									I declare that the information given in Part A is true and correct to the best of my knowledge.
								</label>
							</div>
							<div class="rf-hint mt-2" style="margin-left:0;">
								Declared by {{ auth()->user()->name }} ({{ ucwords(str_replace('-', ' ', auth()->user()->role->role ?? '')) }}), {{ now()->format('d M Y') }}.
							</div>
						</div>

						<div class="text-right">
							<button type="submit" name="action" value="next" class="btn btn-success">Save &amp; Next: Details <i class="fas fa-arrow-right"></i></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	// Radios can't be cleared natively. For the optional single-choice
	// questions and the YES/NO follow-ups, clicking the one that's already
	// picked puts it back to unanswered - "was checked" has to be recorded on
	// mousedown, because by the time click fires the browser has already
	// checked it.
	$(document).on('mousedown', 'input.rf-toggle', function () {
		$(this).data('wasChecked', this.checked);
	}).on('click', 'input.rf-toggle', function () {
		if ($(this).data('wasChecked')) {
			this.checked = false;
			$(this).data('wasChecked', false);
		}
	});

	// A form this long shouldn't lose work to a stray Enter key in a text
	// field, which would otherwise submit whichever button comes first.
	$('#part-a-form').on('keydown', 'input[type=text]', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); }
	});

	@if($errors->any())
	var firstBad = $('.rf-q.has-error').first();
	if (firstBad.length) {
		$('html, body').animate({ scrollTop: firstBad.offset().top - 90 }, 300);
	}
	@endif
});
</script>
@endsection
