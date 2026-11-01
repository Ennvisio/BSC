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
			@include('partials.requisition-form-answers', ['questions' => $questions, 'answerSource' => $formPart])

			@if(!empty($formPart->declaration))
			<div class="rf-none mt-2">
				Declared by {{ $formPart->declaration['declared_by'] ?? '' }}
				@if(!empty($formPart->declaration['declared_by_role']))({{ ucwords(str_replace('-', ' ', $formPart->declaration['declared_by_role'])) }})@endif
				on {{ \Carbon\Carbon::parse($formPart->declaration['declared_at'])->format('d M Y, h:i A') }}.
			</div>
			@endif

			{{-- Set only when Master/Chief Engineer corrected the ship's own
				 answers above (see Order::canEditPartA) - the declaration itself
				 stays theirs, this just notes that something was later fixed. --}}
			@if($formPart->edited_by)
			<div class="rf-none mt-1">
				Last corrected by {{ optional($formPart->editedBy)->name ?? '—' }}
				on {{ optional($formPart->edited_at)->format('d M Y, h:i A') }}.
				@if($formPart->edits->isNotEmpty())
				<a href="#" data-toggle="collapse" data-target="#form-part-{{ strtolower($part) }}-history" aria-expanded="false">
					View original answer{{ $formPart->edits->count() > 1 ? 's' : '' }}
				</a>
				@endif
			</div>
			@endif

			{{-- What the ship (or whoever last held it) actually answered
				 before each correction - kept so a fix to what Master/Chief
				 Engineer saw never quietly erases what was originally declared. --}}
			@if($formPart->edits->isNotEmpty())
			<div class="collapse mt-2" id="form-part-{{ strtolower($part) }}-history">
				@foreach($formPart->edits->reverse() as $edit)
				<div class="rf-ro" style="border-top:1px dashed var(--srd-border); padding-top:10px; margin-top:10px;">
					<div class="rf-none mb-2">
						<b>Before the correction by {{ optional($edit->editedBy)->name ?? '—' }}
						on {{ $edit->edited_at->format('d M Y, h:i A') }}:</b>
					</div>
					@include('partials.requisition-form-answers', ['questions' => $questions, 'answerSource' => $edit])
				</div>
				@endforeach
			</div>
			@endif
		</div>
	</div>
</div>
@endif
