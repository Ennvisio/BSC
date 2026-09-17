@extends('layouts.admin-master')
@section('main-content')
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">New service requisition for &nbsp;
						<span style="color:red;">{{auth()->user()->role->vessel->name}}</span>
					</strong>
				</div>
				<div class="card-body">
					{{-- UI only for now - this doesn't submit anywhere yet, see the
						 JS below. Not a real <form> action so there's nothing to
						 accidentally 404 into. --}}
					<div class="alert alert-info" role="note">
						<i class="fas fa-info-circle"></i>
						This is a preview of the service requisition form - submitting isn't wired up yet.
					</div>

					<form id="service-requisition-form" method="POST" action="#">
						@csrf

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="service_type">Service type <span class="text-danger">*</span></label>
								<select class="form-control" name="service_type" id="service_type" required>
									<option value="">Choose…</option>
									<option value="certificate_servicing">Certificate Servicing</option>
									<option value="survey">Survey</option>
									<option value="equipment_maintenance">Equipment Maintenance</option>
									<option value="it_support">IT Support</option>
									<option value="other">Other</option>
								</select>
							</div>
							<div class="col-md-5">
								<label for="due_date">Due date</label>
								<input type="text" class="form-control date" name="due_date" id="due_date" autocomplete="off" placeholder="Click to choose a date">
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="reference_title">What needs servicing? <span class="text-danger">*</span></label>
								<input type="text" class="form-control" name="reference_title" id="reference_title" placeholder="e.g. IOPP Certificate — Annual Survey, Liferaft Annual Service" required>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="description">Description <span class="text-danger">*</span></label>
								<textarea class="form-control" name="description" id="description" rows="4" placeholder="Scope of the work - what's expiring, what needs doing, and why" required></textarea>
							</div>
						</div>

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="vendor_name">Vendor / service provider</label>
								<input type="text" class="form-control" name="vendor_name" id="vendor_name" placeholder="Optional - if already known">
							</div>
							<div class="col-md-5">
								<label for="estimated_cost">Estimated cost</label>
								<input type="number" step="0.01" min="0" class="form-control" name="estimated_cost" id="estimated_cost" placeholder="Optional">
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="attachments">Attachments</label>
								<input type="file" class="form-control-file" name="attachments[]" id="attachments" multiple>
								<small class="form-text text-muted">e.g. the certificate page that's expiring, a quotation from the service provider.</small>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11 text-right">
								<a href="{{ url('/home') }}" class="btn btn-outline-secondary">Cancel</a>
								<button type="submit" class="btn btn-success">Submit Service Requisition</button>
							</div>
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
	// UI only, per the current build step - there's no backend for this yet
	// (see ServiceRequisitionController), so submitting just says so rather
	// than silently doing nothing or 404ing on a POST route that isn't there.
	$('#service-requisition-form').on('submit', function (e) {
		e.preventDefault();
		swal('Preview only', 'This form isn\'t wired up to save yet - the service requisition workflow is still being built.', 'info');
	});
});
</script>
@endsection
