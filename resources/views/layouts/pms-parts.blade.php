@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Required Parts &mdash; {{ $vessel->name }}</strong>
		</div>
		<div class="card-body">
			<div class="pms-toolbar">
				@if($vessels->isNotEmpty())
				<form method="GET" action="{{ route('pms.parts.index') }}">
					@if($pending)<input type="hidden" name="filter" value="pending">@endif
					<select name="vessel_id" class="form-control" id="pms-vessel-picker" aria-label="Vessel">
						@foreach($vessels as $v)
						<option value="{{ $v->id }}" {{ $v->id === $vessel->id ? 'selected' : '' }}>{{ $v->name }}</option>
						@endforeach
					</select>
				</form>
				@endif
				<div class="pms-filters" role="group" aria-label="Filter parts">
					<a href="{{ route('pms.parts.index', ['vessel_id' => $vessel->id]) }}" class="pms-filter {{ $pending ? '' : 'active' }}">All parts{{ $pending ? '' : ' ' }}@unless($pending)<b>{{ $totalParts }}</b>@endunless</a>
					<a href="{{ route('pms.parts.index', ['vessel_id' => $vessel->id, 'filter' => 'pending']) }}" class="pms-filter {{ $pending ? 'active' : '' }}">Not yet requisitioned @if($pending)<b>{{ $totalParts }}</b>@endif</a>
				</div>
			</div>
			<p class="pms-note">The parts needed by this vessel's open jobs, grouped by job. Open a job to add parts or raise a requisition.</p>

			@forelse($groups as $group)
			@php $job = $group['job']; @endphp
			<section class="pp-group">
				<div class="pp-group-head">
					<h3>{{ $job->definition->name ?? 'Job' }}</h3>
					<span class="pms-muted">{{ $job->component->name ?? '' }}</span>
					<span class="pms-status pms-status-{{ $job->status }}">{{ ['DueSoon' => 'Due Soon', 'WaitingApproval' => 'Waiting Approval'][$job->status] ?? $job->status }}</span>
					<span class="pms-muted">Due {{ $job->due_date ? $job->due_date->format('d M Y') : '—' }}</span>
					<span style="flex:1 1 auto;"></span>
					<a href="{{ route('pms.jobs.index', ['vessel_id' => $vessel->id, 'open' => $job->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-wrench"></i> Open job</a>
				</div>
				<div class="table-responsive">
					<table class="table table-sm mb-0 pp-table">
						<thead><tr><th>Part</th><th>Article / Part / Drawing</th><th>Maker</th><th class="pms-num">Qty</th><th class="pms-num">Est. Price</th><th class="pms-num">Est. Delivery</th><th>Requisition</th></tr></thead>
						<tbody>
							@foreach($group['parts'] as $part)
							@php $info = $part->order ? $orderLabel($part->order) : null; @endphp
							<tr>
								<td>{{ $part->part_name }}@unless($part->item_id) <span class="pms-flag pms-flag-inactive">custom</span>@endunless</td>
								<td>{{ collect([$part->article_no, $part->part_no, $part->drawing_no])->filter()->implode(' · ') ?: '—' }}</td>
								<td>{{ $part->manufacturer->name ?? '—' }}</td>
								<td class="pms-num">{{ $part->quantity }} {{ $part->unit }}</td>
								<td class="pms-num">{{ $part->est_price !== null ? number_format((float) $part->est_price, 2) : '—' }}</td>
								<td class="pms-num">{{ $part->est_delivery_weeks !== null ? $part->est_delivery_weeks.' wk' : '—' }}</td>
								<td>
									@if($info)
									<a href="{{ $info['url'] }}">{{ $info['req_no'] ?: 'Draft' }}</a><span class="pp-sub">{{ $info['status'] }}</span>
									@else
									<span class="pms-muted">Not requisitioned</span>
									@endif
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</section>
			@empty
			<p class="pms-location-empty">{{ $pending ? 'Every required part of the open jobs is already on a requisition.' : 'No open job on this vessel lists any required parts.' }}</p>
			@endforelse
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () { $('#pms-vessel-picker').on('change', function () { this.form.submit(); }); });
</script>
@endsection
