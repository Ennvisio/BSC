{{-- Shared by every role that gets a personal approval-chain dashboard -
	 currently GM (SRD) on home.blade.php and DGM/AGM/AM (SSM) on
	 layouts/order.blade.php. Expects $stats with these four keys, each
	 read off the SAME view instances the destination pages themselves
	 build (see HomeController@index) so a card's number can never drift
	 from the list it links to. --}}
{{-- Item and service requisitions are different modules with different
	 stages, so each gets its own labelled row of cards ($serviceStats is only
	 passed for roles that see service requisitions). --}}
<div class="col-lg-12">
	<div class="srd-stat-section"><i class="fas fa-list-alt"></i> Item Requisition</div>
</div>
<div class="col-lg-12">
	{{-- No mb-3 on the row itself: every card column already carries one, so
		 adding it here made the gap under this row larger than between the
		 rows that follow. --}}
	<div class="row">
		<div class="col-6 col-md-3 mb-3">
			<a href="{{url('/pending/requisition')}}" class="text-decoration-none">
				<div class="srd-stat-card">
					<div class="srd-stat-icon is-blue"><i class="fas fa-hourglass-half"></i></div>
					<div class="srd-stat-body">
						<div class="srd-stat-label">Pending My Action</div>
						<div class="srd-stat-value">{{ $stats['pending_action'] }}</div>
					</div>
				</div>
			</a>
		</div>
		<div class="col-6 col-md-3 mb-3">
			<a href="{{ route('my.approvals') }}" class="text-decoration-none">
				<div class="srd-stat-card">
					<div class="srd-stat-icon is-indigo"><i class="fas fa-check-circle"></i></div>
					<div class="srd-stat-body">
						<div class="srd-stat-label">My Approvals</div>
						<div class="srd-stat-value">{{ $stats['my_approvals'] }}</div>
					</div>
				</div>
			</a>
		</div>
		<div class="col-6 col-md-3 mb-3">
			<a href="{{url('/approved/requisition')}}" class="text-decoration-none">
				<div class="srd-stat-card">
					<div class="srd-stat-icon is-green"><i class="fas fa-truck"></i></div>
					<div class="srd-stat-body">
						<div class="srd-stat-label">Approved</div>
						<div class="srd-stat-value">{{ $stats['approved'] }}</div>
					</div>
				</div>
			</a>
		</div>
		<div class="col-6 col-md-3 mb-3">
			<a href="{{url('/received/requisition')}}" class="text-decoration-none">
				<div class="srd-stat-card">
					<div class="srd-stat-icon is-slate"><i class="fas fa-inbox"></i></div>
					<div class="srd-stat-body">
						<div class="srd-stat-label">Received</div>
						<div class="srd-stat-value">{{ $stats['received'] }}</div>
					</div>
				</div>
			</a>
		</div>
	</div>
</div>

@if(!empty($serviceStats))
<div class="col-lg-12">
	<div class="srd-stat-section"><i class="fas fa-wrench"></i> Service Requisition</div>
</div>
<div class="col-lg-12">
	<div class="row">
		@php
			// SRD officers act on service requisitions, so for them the first card
			// is "waiting on me" and they have My Approvals; SSM officers only
			// follow them, so theirs is the plain Pending total and no My Approvals.
			$srdLevel = $serviceStats['my_approvals'] !== null;
			$serviceCards = array_values(array_filter([
				['route' => route('service-requisition.pending'), 'icon' => 'fa-hourglass-half', 'tone' => 'is-blue',
					'label' => $srdLevel ? 'Pending My Action' : 'Pending', 'value' => $srdLevel ? $serviceStats['pending_action'] : $serviceStats['pending']],
				$srdLevel ? ['route' => route('service-requisition.my-approvals'), 'icon' => 'fa-check-circle', 'tone' => 'is-indigo',
					'label' => 'My Approvals', 'value' => $serviceStats['my_approvals']] : null,
				['route' => route('service-requisition.approved'), 'icon' => 'fa-clipboard', 'tone' => 'is-green',
					'label' => 'Approved', 'value' => $serviceStats['approved']],
				['route' => route('service-requisition.rejected'), 'icon' => 'fa-times-circle', 'tone' => 'is-pink',
					'label' => 'Rejected', 'value' => $serviceStats['rejected']],
			]));
		@endphp
		@foreach($serviceCards as $card)
		<div class="col-6 col-md-3 mb-3">
			<a href="{{ $card['route'] }}" class="text-decoration-none">
				<div class="srd-stat-card">
					<div class="srd-stat-icon {{ $card['tone'] }}"><i class="fas {{ $card['icon'] }}"></i></div>
					<div class="srd-stat-body">
						<div class="srd-stat-label">{{ $card['label'] }}</div>
						<div class="srd-stat-value">{{ $card['value'] }}</div>
					</div>
				</div>
			</a>
		</div>
		@endforeach
	</div>
</div>
@endif
