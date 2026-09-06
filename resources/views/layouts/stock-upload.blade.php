@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Update Stock &mdash; {{ $vessel->name ?? '' }}</strong>
			<div class="right-buttons">
				<a href="{{ route('stock.history') }}" class="btn btn-sm btn-info"><i class="fas fa-history"></i> Upload History</a>
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

			<div class="row">
				<div class="col-md-6 border-right">
					<h6 class="mb-2">Step 1 &mdash; Download the sheet</h6>
					<p class="text-muted small">
						Pick a category and download the sheet. It comes pre-filled with every item in your
						vessel's catalog for that category, along with the stock figure currently on record.
					</p>
					<form action="{{ route('stock.template') }}" method="GET">
						<div class="form-group">
							<label>Category</label>
							<select name="category_id" class="form-control" required>
								<option value="">Select category</option>
								@foreach($categories as $category)
								<option value="{{$category->id}}">{{$category->name}}</option>
								@endforeach
							</select>
						</div>
						<button type="submit" class="btn btn-sm btn-info"><i class="fas fa-download"></i> Download Sheet</button>
					</form>
				</div>

				<div class="col-md-6">
					<h6 class="mb-2">Step 2 &mdash; Fill in and upload</h6>
					<p class="text-muted small">
						Enter what is physically on board in the <b>Stock Qty</b> column. Use
						<b>Opening Stock</b> for the vessel's declared starting balance &mdash; it is a
						separate figure that stays put as stock moves, and it is what prints in the
						Opening Stock column of a requisition. <b>Minimum Qty</b> is optional and warns
						you when an item runs low.
						<b>Any cell you leave blank is not changed</b> &mdash; each column is judged on its
						own, so you can fill in only what you have counted and upload the sheet as many
						times as you like.
					</p>
					<form action="{{ route('stock.upload.store') }}" method="POST" enctype="multipart/form-data">
						@csrf
						<div class="form-group">
							<label>Category <span class="text-muted small">(optional, for your own records)</span></label>
							<select name="category_id" class="form-control">
								<option value="">Not specified</option>
								@foreach($categories as $category)
								<option value="{{$category->id}}">{{$category->name}}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label>Filled-in sheet (.xlsx, .xls, .csv)</label>
							<input type="file" name="stock_file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
						</div>
						<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Upload Stock</button>
					</form>
				</div>
			</div>

			<hr>
			<p class="text-muted small mb-0">
				Stock is also topped up automatically whenever you confirm receipt of a delivered
				requisition &mdash; you only need this screen for opening balances and corrections.
			</p>
		</div>
	</div>
</div>
@endsection
