@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Stock Upload History</strong>
			<div class="right-buttons">
				<a href="{{ route('stock.upload.form') }}" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Update Stock</a>
			</div>
		</div>
		<div class="card-body">
			@if(session('message'))
			<div class="alert alert-info">{{ session('message') }}</div>
			@endif

			<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
				<thead>
					<th>#</th>
					<th>File</th>
					<th>Category</th>
					<th>Uploaded By</th>
					<th>Status</th>
					<th>Rows</th>
					<th>Updated</th>
					<th>Unchanged</th>
					<th>Failed</th>
					<th>Date</th>
					<th>Errors</th>
				</thead>
				<tbody>
					@forelse($imports as $import)
					<tr>
						<td>{{$loop->iteration}}</td>
						<td>{{$import->filename}}</td>
						<td>{{!empty($import->category->name) ? $import->category->name : ''}}</td>
						<td>{{!empty($import->uploadedBy->name) ? $import->uploadedBy->name : ''}}</td>
						<td>
							@if($import->status == 'completed')
							<span class="badge badge-success">Completed</span>
							@elseif($import->status == 'completed_with_errors')
							<span class="badge badge-warning">Completed with errors</span>
							@elseif($import->status == 'failed')
							<span class="badge badge-danger">Failed</span>
							@else
							<span class="badge badge-secondary">{{ucfirst($import->status)}}</span>
							@endif
						</td>
						<td>{{$import->row_count}}</td>
						<td>{{$import->updated_count}}</td>
						<td>{{$import->skipped_count}}</td>
						<td>{{$import->failed_count}}</td>
						<td>{{$import->created_at->format('Y-m-d H:i')}}</td>
						<td>
							@if(!empty($import->error_log))
							<button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#stock-error-log-{{$import->id}}">View</button>
							<div class="modal fade" id="stock-error-log-{{$import->id}}" tabindex="-1" role="dialog">
								<div class="modal-dialog modal-lg" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title">Errors — {{$import->filename}}</h5>
											<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
										</div>
										<div class="modal-body">
											<pre style="white-space:pre-wrap;">{{$import->error_log}}</pre>
										</div>
									</div>
								</div>
							</div>
							@endif
						</td>
					</tr>
					@empty
					<tr><td colspan="11" class="text-center">No stock uploads yet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>
@endsection
