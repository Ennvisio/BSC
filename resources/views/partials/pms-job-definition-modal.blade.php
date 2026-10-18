{{-- Add / edit a job definition. Needs $ranks and $jobTypes; driven by
	 partials.pms-job-definition-script (window.PmsJobDef). --}}
<div class="modal fade" id="pms-jd-modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="pms-jd-form" autocomplete="off" novalidate>
				@csrf
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title"><i class="fas fa-wrench"></i> &nbsp; <span id="pms-jd-title">Add Job</span></legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<p class="pms-note mb-2" id="pms-jd-component"></p>
					<ul class="nav nav-tabs mb-3" role="tablist">
						<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#jd-tab-job" role="tab">Job</a></li>
						<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#jd-tab-parts" role="tab"><i class="fas fa-cubes"></i> Required Parts <span id="jd-parts-count" class="pms-muted"></span></a></li>
					</ul>
					<div class="tab-content">
					<div class="tab-pane fade show active" id="jd-tab-job" role="tabpanel">

					<h6 class="pms-section">Job</h6>
					<div class="pms-form-grid">
						<div class="form-group full">
							<label for="jd-name">Job Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="jd-name" name="name" maxlength="255" required>
						</div>
						<div class="form-group">
							<label for="jd-type">Job Type <span class="text-danger">*</span></label>
							<select class="form-control" id="jd-type" name="job_type_id" required>
								<option value="" disabled selected>Select…</option>
								@foreach($jobTypes as $type)
								<option value="{{ $type->id }}">{{ $type->name }}{{ $type->abbreviation ? ' ('.$type->abbreviation.')' : '' }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="jd-rank">Responsible Rank <span class="text-danger">*</span></label>
							<select class="form-control" id="jd-rank" name="rank_id" required>
								<option value="" disabled selected>Select…</option>
								@foreach($ranks as $rank)
								<option value="{{ $rank->id }}">{{ $rank->code }} &mdash; {{ $rank->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="jd-performed-by">Performed By</label>
							<select class="form-control" id="jd-performed-by" name="performed_by">
								<option value="Crew">Crew</option>
								<option value="External">External</option>
								<option value="ClassSurveyor">Class Surveyor</option>
							</select>
						</div>
						<div class="form-group">
							<label for="jd-perform-when">Perform When</label>
							<select class="form-control" id="jd-perform-when" name="perform_when">
								<option value="Anytime">Anytime</option>
								<option value="AtSea">At Sea</option>
								<option value="InPort">In Port</option>
								<option value="Docking">Docking</option>
							</select>
						</div>
					</div>

					<h6 class="pms-section">Schedule</h6>
					<div class="pms-seg mb-3" role="radiogroup" aria-label="Recurring or single job">
						<input type="radio" name="jd_kind" id="jd-kind-recurring" value="0" checked>
						<label for="jd-kind-recurring">Recurring</label>
						<input type="radio" name="jd_kind" id="jd-kind-single" value="1">
						<label for="jd-kind-single">Single job</label>
					</div>
					<input type="hidden" name="is_single" id="jd-is-single" value="0">

					<div id="jd-recurring">
						<label>Intervals <span class="text-danger">*</span> <span class="pms-muted" style="font-weight:400;">&mdash; with more than one, the job is due at whichever comes first</span></label>
						<div id="jd-intervals"></div>
						<button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="jd-add-interval"><i class="fas fa-plus"></i> Add interval</button>
						<div class="pms-form-grid">
							<div class="form-group">
								<label for="jd-start">Start Date</label>
								<input type="date" class="form-control" id="jd-start" name="start_date">
								<small class="form-text pms-muted">The first due date counts from here. Blank: the installation date, else today.</small>
							</div>
						</div>
					</div>

					<div id="jd-single" class="pms-hidden">
						<div class="pms-form-grid">
							<div class="form-group">
								<label for="jd-due-type">Due By <span class="text-danger">*</span></label>
								<select class="form-control" id="jd-due-type" name="due_type">
									<option value="Date">Date</option>
									<option value="RunningHours" class="jd-rh-only">Running hours</option>
								</select>
							</div>
							<div class="form-group" id="jd-due-date-group">
								<label for="jd-due-date">Due Date <span class="text-danger">*</span></label>
								<input type="date" class="form-control" id="jd-due-date" name="due_date">
							</div>
							<div class="form-group pms-hidden" id="jd-due-rh-group">
								<label for="jd-due-rh">Due Running Hours <span class="text-danger">*</span></label>
								<input type="number" class="form-control" id="jd-due-rh" name="due_running_hours" min="0" step="0.1">
							</div>
						</div>
					</div>

					<div class="pms-form-grid">
						<div class="form-group">
							<label for="jd-warning">Warn Before Due</label>
							<div class="input-group">
								<input type="number" class="form-control" id="jd-warning" name="warning_period" min="0" max="65535" step="1" value="7">
								<select class="form-control" id="jd-warning-unit" name="warning_unit">
									<option value="Days">Days</option>
									<option value="Weeks">Weeks</option>
									<option value="Months">Months</option>
									<option value="RunningHours" class="jd-rh-only">Running hours</option>
								</select>
							</div>
						</div>
					</div>

					<h6 class="pms-section">Details</h6>
					<div class="pms-form-grid">
						<div class="form-group">
							<label for="jd-internal">Internal Code</label>
							<input type="text" class="form-control" id="jd-internal" name="internal_code" maxlength="255">
						</div>
						<div class="form-group">
							<label for="jd-class-code">Class Code</label>
							<input type="text" class="form-control" id="jd-class-code" name="class_code" maxlength="255">
						</div>
						<div class="form-group">
							<label for="jd-staff">Staff Needed</label>
							<input type="number" class="form-control" id="jd-staff" name="staff_needed" min="0" max="999" step="1">
						</div>
						<div class="form-group">
							<label for="jd-hours">Time Needed</label>
							<div class="input-group">
								<input type="number" class="form-control" id="jd-hours" name="hours_needed" min="0" max="9999" step="1" placeholder="h" aria-label="Hours needed">
								<input type="number" class="form-control" id="jd-minutes" name="minutes_needed" min="0" max="59" step="1" placeholder="min" aria-label="Minutes needed">
							</div>
						</div>
						<div class="form-group full">
							<label for="jd-description">Description / Instructions</label>
							<textarea class="form-control" id="jd-description" name="description" rows="3" maxlength="10000"></textarea>
						</div>
						<div class="form-group full">
							<div class="pms-checks">
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="jd-approval" name="requires_approval" value="1">
									<label class="custom-control-label" for="jd-approval">Needs office approval</label>
								</div>
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="jd-critical" name="is_critical" value="1">
									<label class="custom-control-label" for="jd-critical">Critical</label>
								</div>
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="jd-class" name="is_class_relevant" value="1">
									<label class="custom-control-label" for="jd-class">Class relevant</label>
								</div>
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="jd-safety" name="is_safety_relevant" value="1">
									<label class="custom-control-label" for="jd-safety">Safety relevant</label>
								</div>
								<div class="custom-control custom-checkbox" id="jd-active-wrap">
									<input type="checkbox" class="custom-control-input" id="jd-active" name="is_active" value="1" checked>
									<label class="custom-control-label" for="jd-active">Active</label>
								</div>
							</div>
						</div>
					</div>

					<h6 class="pms-section">Checklist</h6>
					<ol class="jd-checklist" id="jd-checklist"></ol>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="jd-add-check"><i class="fas fa-plus"></i> Add checklist item</button>

					<div id="jd-jobs-wrap" class="pms-hidden">
						<h6 class="pms-section">Generated Jobs</h6>
						<div class="table-responsive">
							<table class="table table-sm table-bordered mb-0">
								<thead><tr><th>Status</th><th>Due Date</th><th>Original Due</th><th class="pms-num">Due RH</th><th>Completed</th><th>Done By</th></tr></thead>
								<tbody id="jd-jobs"></tbody>
							</table>
						</div>
					</div>
					</div>
					<div class="tab-pane fade" id="jd-tab-parts" role="tabpanel">
						<p class="pms-note">Parts this job usually needs. Each job made from this definition starts with a copy, which can then be changed for that job alone.</p>
						<div class="table-responsive">
							<table class="table table-sm table-bordered pp-table mb-2">
								<thead><tr><th>Part</th><th class="pms-num">Qty</th><th>Unit</th><th class="pms-num">Est. Price</th><th class="pms-num">Est. Delivery</th><th class="action"></th></tr></thead>
								<tbody id="jd-parts"></tbody>
							</table>
						</div>
						<button type="button" class="btn btn-sm btn-outline-secondary" id="jd-add-part"><i class="fas fa-plus"></i> Add part</button>
					</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="jd-save">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>
