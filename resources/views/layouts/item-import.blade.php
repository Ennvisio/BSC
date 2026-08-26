@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Import Catalog</strong>

			<div class="right-buttons">
				<a href="{{url('/catalog/import/history')}}" class="btn btn-info"><i class="fas fa-history"></i> Import History</a>
			</div>
		</div>
		<div class="card-body">
			@if(session('message'))
			<div class="alert alert-info">{{ session('message') }}</div>
			@endif

			@if($errors->any())
			<div class="alert alert-danger">
				<ul class="mb-0">
					@foreach($errors->all() as $error)
					<li>{{ $error }}</li>
					@endforeach
				</ul>
			</div>
			@endif

			<p class="text-muted">
				Upload an Excel/CSV catalog for one vessel. Expected columns: <b>Item Path</b> (a "->"-delimited
				hierarchy, e.g. <code>00. Provisions-&gt;Beans and Peas, Dry-&gt;BEANS BROAD DRY</code>),
				<b>Item Name</b>, <b>Article Number</b>, Unit Code, Account Number, Description, Part Number,
				Drawing Number, HS Code, Manufacturer. Re-uploading the same file is safe — items are matched
				by Article Number and updated, not duplicated.
			</p>

			<form action="{{url('/catalog/import')}}" method="POST" enctype="multipart/form-data">
				@csrf
				<div class="form-group">
					<label>Vessel</label>
					<select name="vessel_id" class="form-control" required>
						<option value="">Select vessel</option>
						@foreach($vessels as $vessel)
						<option value="{{$vessel->id}}">{{$vessel->name}}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group">
					<label>Catalog file (.xlsx, .xls, .csv)</label>
					<input type="file" name="catalog_file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
				</div>
				<button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
			</form>
		</div>
	</div>
</div>
@endsection
