{{-- One saved part of the approval form, read-only. Takes $order and $part
     ('A'); renders nothing if that part was never saved. Everything comes from
     RequisitionForm's definition, so Parts B and C will render through this
     same partial once they have definitions. --}}
@php
	$formPart = $order->formPart($part);
	$questions = \App\RequisitionForm::questions($part);
@endphp
@if($formPart && $formPart->isComplete() && !empty($questions))
@include('partials.requisition-form-styles')
<div class="od-card od-reason-card rf-ro" id="form-part-{{ strtolower($part) }}">
	<div class="od-card-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px; cursor:pointer;"
		data-toggle="collapse" data-target="#form-part-{{ strtolower($part) }}-body" aria-expanded="false">
		<h2>{{ \App\RequisitionForm::PARTS[$part] }}</h2>
		<span style="font-size:12px; color:var(--srd-muted);">
			Filled by {{ $formPart->declaration['declared_by'] ?? ($formPart->filledBy->name ?? '—') }}
			· {{ optional($formPart->completed_at)->format('d M Y') }}
			<i class="fas fa-chevron-down ml-1"></i>
		</span>
	</div>
	{{-- Collapsed by default: eleven cards of answers above the items table
	     would push what approvers came here to see off the screen. --}}
	<div class="collapse" id="form-part-{{ strtolower($part) }}-body">
		<div class="od-card-body">
			@foreach($questions as $q)
			@php
				$a = $formPart->answerFor($q['key']);
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

			@if(!empty($formPart->declaration))
			<div class="rf-none mt-2">
				Declared by {{ $formPart->declaration['declared_by'] ?? '' }}
				@if(!empty($formPart->declaration['declared_by_role']))({{ ucwords(str_replace('-', ' ', $formPart->declaration['declared_by_role'])) }})@endif
				on {{ \Carbon\Carbon::parse($formPart->declaration['declared_at'])->format('d M Y, h:i A') }}.
			</div>
			@endif
		</div>
	</div>
</div>
@endif
