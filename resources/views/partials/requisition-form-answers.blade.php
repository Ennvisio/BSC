{{-- One answer set rendered read-only. Takes $questions (RequisitionForm::questions($part))
	 and $answerSource (anything with an answerFor($key) method - OrderFormPart for the
	 current answers, OrderFormPartEdit for an earlier snapshot). Pulled out of
	 requisition-form-part.blade.php so a history entry can reuse the exact same
	 markup as the live answers above it. --}}
@foreach($questions as $q)
@php
	$a = $answerSource->answerFor($q['key']);
	$chosen = array_values(array_filter((array) $a['choice'], fn ($v) => $v !== null && $v !== ''));
	$filledFields = array_filter($a['fields'] ?? [], fn ($v) => $v !== '' && $v !== null);
@endphp
<div class="rf-q">
	<div class="rf-q-title">
		<span class="rf-q-no">{{ $q['no'] }}.</span>
		@if(!empty($q['bn'])){{ $q['bn'] }} / @endif{{ $q['en'] }}
	</div>

	@if(count($chosen))
	<div class="rf-opts">
		@foreach($chosen as $value)
		<span class="rf-chip-static">{{ \App\RequisitionForm::optionLabel($q, $value) }}</span>
		@endforeach
	</div>
	@else
	<span class="rf-none">Not answered</span>
	@endif

	@if(count($filledFields))
	<div class="mt-2">
		@foreach($q['fields'] as $field)
		@if(!empty($a['fields'][$field['key']]))
		<span class="rf-kv"><b>{{ $field['label'] }}:</b> {{ $a['fields'][$field['key']] }}</span>
		@endif
		@endforeach
	</div>
	@endif

	@if(!empty($q['followup']) && !empty($a['followup']))
	<div class="mt-2">
		<span class="rf-kv"><b>{{ $q['followup']['label'] }}</b>
			<span class="rf-chip-static ml-1">{{ $q['followup']['options'][$a['followup']] ?? $a['followup'] }}</span>
		</span>
	</div>
	@endif
</div>
@endforeach
