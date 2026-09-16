{{-- Shared by every role that gets a personal approval-chain dashboard -
	 currently GM (SRD) on home.blade.php and DGM/AGM/AM (SSM) on
	 layouts/order.blade.php. Expects $stats with these four keys, each
	 read off the SAME view instances the destination pages themselves
	 build (see HomeController@index) so a card's number can never drift
	 from the list it links to. --}}
<div class="col-lg-12">
	<div class="row mb-3">
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
