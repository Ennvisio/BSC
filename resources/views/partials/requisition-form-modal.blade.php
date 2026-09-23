{{-- One part of the approval form, as a fill-in modal. Takes $order and
	 $part ('B' or 'C').

	 A modal rather than its own page: these parts are answered while reading
	 the requisition, so sending the officer away from it and back would lose
	 the very thing they are verifying against. The question markup matches the
	 wizard's Part A (partials/requisition-form-styles), so all three parts
	 look like one document. --}}
@include('partials.requisition-form-styles')
<div class="modal fade" id="part-{{ strtolower($part) }}-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<div style="font-size:11.5px;color:#6b7a82;font-weight:600;margin-bottom:2px;">
						{{ $order->req_no ?: 'This requisition' }}
					</div>
					<h5 class="modal-title">{{ \App\RequisitionForm::PARTS[$part] }}</h5>
					<div style="font-size:12px;color:#6b7a82;font-style:italic;margin-top:3px;">
						{{ \App\RequisitionForm::PART_SUBTITLES[$part] }}
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body" style="max-height:65vh; overflow-y:auto;">
				<div class="alert alert-danger part-form-errors" style="display:none;"></div>
				<form class="part-form" data-part="{{ $part }}">
					@foreach(\App\RequisitionForm::questions($part) as $q)
					@php $name = 'q['.$q['key'].']'; @endphp
					<div class="rf-q">
						<div class="rf-q-title">
							<span class="rf-q-no">{{ $q['no'] }}.</span>{{ $q['en'] }}
							@if($q['required'] ?? false)<span class="rf-req" title="Required">*</span>@endif
						</div>
						{{-- Some questions are purely a record of what was found and
							 carry no choices at all, only fields. --}}
						@if(!empty($q['options']))
						<div class="rf-opts">
							@foreach($q['options'] as $value => $label)
							<label class="rf-chip">
								<input type="radio" class="rf-toggle" name="{{ $name }}[choice]" value="{{ $value }}">
								<span>{{ $label }}</span>
							</label>
							@endforeach
						</div>
						@endif
						@if(!empty($q['fields']))
						<div class="rf-fields">
							@foreach($q['fields'] as $field)
							<div class="rf-field">
								<label for="p{{ strtolower($part) }}-f-{{ $q['key'] }}-{{ $field['key'] }}">{{ $field['label'] }}</label>
								<input type="text" class="form-control"
									id="p{{ strtolower($part) }}-f-{{ $q['key'] }}-{{ $field['key'] }}"
									name="{{ $name }}[fields][{{ $field['key'] }}]"
									maxlength="{{ \App\RequisitionForm::MAX_FIELD_LENGTH }}" autocomplete="off">
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
									<input type="radio" class="rf-toggle" name="{{ $name }}[followup]" value="{{ $value }}">
									<span>{{ $label }}</span>
								</label>
								@endforeach
							</div>
						</div>
						@endif
					</div>
					@endforeach
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary part-form-submit"
					data-id="{{ $order->id }}" data-part="{{ $part }}">
					<i class="fas fa-check-circle"></i> Submit Part {{ $part }}
				</button>
			</div>
		</div>
	</div>
</div>
