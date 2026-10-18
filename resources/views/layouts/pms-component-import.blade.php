@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Import Components &mdash; {{ $vessel->name }}</strong>
			<div class="right-buttons">
				<a href="{{ route('pms.components.index', ['vessel_id' => $vessel->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sitemap"></i> Back to components</a>
			</div>
		</div>
		<div class="card-body">
			@if($errors->any())
			<div class="alert alert-danger">{{ $errors->first() }}</div>
			@endif

			<div class="pms-import-grid">
				<form method="POST" action="{{ route('pms.components.import.upload') }}" enctype="multipart/form-data" class="pms-import-form">
					@csrf
					@if($vessels->isNotEmpty())
					<div class="form-group">
						<label for="imp-vessel">Vessel <span class="text-danger">*</span></label>
						<select name="vessel_id" id="imp-vessel" class="form-control">
							@foreach($vessels as $v)
							<option value="{{ $v->id }}" {{ $v->id === $vessel->id ? 'selected' : '' }}>{{ $v->name }}</option>
							@endforeach
						</select>
					</div>
					@endif
					<div class="form-group">
						<label for="imp-file">Excel file <span class="text-danger">*</span></label>
						<input type="file" name="file" id="imp-file" class="form-control-file" accept=".xlsx,.xls" required>
						<small class="form-text pms-muted">Only the sheet named <strong>Components</strong> is read. Up to 10 MB.</small>
					</div>
					<button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload and preview</button>
					<p class="pms-note mt-2 mb-0">Nothing is saved yet &mdash; the next page shows what would be created, skipped or refused.</p>
				</form>

				<div class="pms-import-help">
					<h6 class="pms-section mt-0">How the sheet is read</h6>
					<ul>
						<li><strong>Location</strong> and <strong>Component Name</strong> are required. The Vessel Code column is ignored &mdash; the vessel is the one chosen here.</li>
						<li><strong>Parent Path</strong> empty = a top-level system. Otherwise the parent's names from the top, separated by <code>&gt;</code>, e.g. <code>COMPRESSED AIR SYSTEM &gt; MAIN AIR COMPRESSOR NO.1</code>. The parent may be further down the file, or already on the vessel.</li>
						<li>A component that already exists under the same parent is <strong>skipped</strong>, so the same file can be imported again safely.</li>
						<li>Category and Manufacturer not in the master lists are left empty (a warning). An empty Condition becomes Not Classified.</li>
						<li>Has Counter = Y with a Current RH also records that running-hour reading.</li>
						<li>Any error stops the whole import &mdash; nothing is saved until the file is clean.</li>
					</ul>
					<a href="{{ route('pms.components.import.template') }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Download template</a>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
