@extends('layouts.admin-master')
@section('main-content')
{{-- IBM Plex Mono/Sans aren't loaded by admin-master.blade.php (which has no
     head-yield to add them to), so they're pulled in here instead - a
     stylesheet <link> works wherever it sits in the document, head or not. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ---- Order detail page: design tokens ----
   Palette and type scale for this page only - doesn't touch the sidebar/
   topbar chrome (css/srd-theme.css), which every other page also uses. */
.order-section{
	--od-ink:        #15211F;
	--od-ink-soft:   #24312F;
	--od-muted:      #6B7877;
	--od-muted-2:    #778483;
	--od-faint:      #93A0A0;
	--od-line:       #DFE5E4;
	--od-line-soft:  #ECF0EF;
	--od-surface:    #FFFFFF;
	--od-ground:     #F7F9F9;
	--od-accent:     #0E7C74;
	--od-accent-dark:#0A5D57;
	--od-accent-tint:#EAF6F4;
	--od-warn:       #C2700C;
	--od-warn-dark:  #8A4B08;
	--od-warn-tint:  #FFF4E5;
	--od-danger:     #B3261E;
	--od-radius:     12px;
	--od-radius-sm:  8px;
	--od-sans:       'IBM Plex Sans', 'Helvetica Neue', Helvetica, sans-serif;
	--od-mono:       'IBM Plex Mono', ui-monospace, monospace;
	font-family: var(--od-sans);
	color: var(--od-ink);
}
.order-section .card.order-card{
	border: none;
	background: transparent;
	box-shadow: none;
}
.order-section .card-body{ padding: 0; }

/* Full width of the content area rather than Bootstrap's centred .container,
   matching the design's fluid main column - .srd-content already supplies
   the page gutter. min-width:0 on the row/col so a wide table can't push
   the whole page sideways instead of scrolling inside its own box. */
.order-section.container{ max-width: 100%; width: 100%; padding-left: 0; padding-right: 0; }
.order-section > .row{ margin-left: 0; margin-right: 0; }
.order-section > .row > [class^="col"]{ padding-left: 0; padding-right: 0; min-width: 0; }

/* Print-only duplicate of the four header fields. It has no .print-header
   class, so it has always rendered on screen too - now redundant next to
   .od-infobar above. The print popup writes its own
   "#order-print-header2{display:flex}" rule, so hiding it here doesn't
   affect what gets printed. */
#order-print-header2{ display: none !important; }

/* ---- Header: eyebrow + title + action buttons ---- */
.od-header{
	display: flex; align-items: flex-start; justify-content: space-between;
	gap: 20px; flex-wrap: wrap; margin-bottom: 20px;
}
.od-eyebrow{
	font-size: 11.5px; letter-spacing: .1em; text-transform: uppercase;
	color: var(--od-muted); font-weight: 600; margin-bottom: 6px;
}
.od-title{
	margin: 0; font-size: 26px; font-weight: 600; letter-spacing: -.02em;
	line-height: 1.15; color: var(--od-ink);
}
.od-actions{ display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.od-actions .btn{
	display: inline-flex; align-items: center; gap: 8px;
	padding: 10px 16px; border-radius: var(--od-radius-sm);
	font-size: 13.5px; font-weight: 600; line-height: 1.2;
	border: 1px solid transparent; white-space: nowrap;
}
.od-actions .btn-primary{ background: var(--od-accent); border-color: var(--od-accent); }
.od-actions .btn-primary:hover, .od-actions .btn-primary:focus{ background: var(--od-accent-dark); border-color: var(--od-accent-dark); }
.od-actions .btn-info{ background: var(--od-ink); border-color: var(--od-ink); color: #fff; }
.od-actions .btn-info:hover, .od-actions .btn-info:focus{ background: #000; border-color: #000; color: #fff; }
.od-actions .btn-outline-secondary, .od-actions .btn-secondary{
	background: var(--od-surface); border-color: #D3DBDA; color: var(--od-ink-soft); font-weight: 500;
}
.od-actions .btn-outline-secondary:hover, .od-actions .btn-secondary:hover{ background: var(--od-ground); }
.od-actions .form-control{
	height: auto; padding: 9px 12px; font-size: 13px; border: 1px solid #D3DBDA;
	border-radius: var(--od-radius-sm); color: var(--od-ink);
}
.od-actions select.form-control{ min-width: 200px; }
.od-receipt-reminder{
	/* .od-header is flex+wrap with two items (title, actions) already in it -
	   flex-basis:100% forces this third item onto its own full-width row
	   at the bottom of the header, rather than squeezing in beside them. */
	flex-basis: 100%;
	margin: 4px 0 0; text-align: right; font-size: 12.5px; color: var(--od-warn-dark);
}
.od-receipt-reminder a{ color: var(--od-accent); font-weight: 600; text-decoration: underline; }
.od-receipt-reminder a:hover{ color: var(--od-accent-dark); }
/* So the anchor jump doesn't land the panel flush against the very top of
   the viewport - a little breathing room above it. */
#procurement-panel{ scroll-margin-top: 20px; }

/* ---- Info bar: Req No / Date / Port / Stage ---- */
.od-infobar{
	display: flex; flex-wrap: wrap; background: var(--od-surface);
	border: 1px solid var(--od-line); border-radius: var(--od-radius);
	margin-bottom: 20px; overflow: hidden;
}
.od-infobar > div{
	flex: 1 1 170px; min-width: 150px; padding: 15px 20px;
	border-right: 1px solid var(--od-line-soft); border-bottom: 1px solid var(--od-line-soft);
}
.od-infobar > div:last-child{ border-right: none; }
.od-infobar .k{
	font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
	color: var(--od-muted-2); font-weight: 600; margin-bottom: 5px;
}
.od-infobar .v{ font-size: 13.5px; color: var(--od-ink); }
.od-infobar .v.mono{ font-family: var(--od-mono); }
.od-stage-pill{
	display: inline-flex; align-items: center; gap: 7px; padding: 5px 10px;
	border-radius: 999px; background: var(--od-warn-tint); color: var(--od-warn-dark);
	font-size: 11.5px; font-weight: 600;
}
.od-stage-pill.is-done{ background: var(--od-accent-tint); color: var(--od-accent-dark); }
.od-stage-pill .dot{ width: 6px; height: 6px; border-radius: 50%; background: var(--od-warn); flex: 0 0 6px; }
.od-stage-pill.is-done .dot{ background: var(--od-accent); }

/* ---- Cards: item table / reason ---- */
.od-card{
	background: var(--od-surface); border: 1px solid var(--od-line);
	border-radius: var(--od-radius); margin-bottom: 20px; overflow: hidden;
}
.od-card-head{ padding: 15px 20px; border-bottom: 1px solid var(--od-line-soft); }
.od-card-head h2{ margin: 0; font-size: 15px; font-weight: 600; color: var(--od-ink); }
.od-card-body{ padding: 20px; }
.od-reason-label{
	font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
	color: var(--od-muted-2); font-weight: 600; margin-bottom: 8px;
}
.od-reason-text{ font-size: 14px; line-height: 1.6; color: var(--od-ink-soft); max-width: 70ch; }
.od-reason-text textarea{ font-family: var(--od-sans); }
.od-reason-card{ margin-bottom: 20px; }

/* ---- Authorisation (signatures) ----
   #order-print-footer1 is copied verbatim into the print popup by
   print-pdf-custom.js, which writes its OWN <style> there targeting
   .signs-master-chief/.master-chief - including a margin-top:-45px that
   made sense for the old layout it was written against. Overridden
   explicitly here for the on-screen card; !important on display because a
   same-specificity .print-header{display:none} rule exists in css/style.css
   and load order between the two isn't worth depending on. */
/* Both selectors hit the SAME element (#order-print-footer1 carries both
   classes), so this one has to set the grid too - an id+class selector
   outranks the plain .signs-master-chief rule below and its display:block
   would otherwise flatten the grid into a single stacked column. */
#order-print-footer1.print-header,
#order-print-footer1.print-header.signs-master-chief{
	display: grid !important;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)) !important;
	gap: 28px 24px !important; margin: 0 !important; padding: 0 !important;
	background: transparent !important; text-align: left !important; border-bottom: none !important;
}
.signs-master-chief{
	display: grid !important; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 28px 24px !important; margin: 0 !important; padding: 0 !important; border-bottom: none !important;
}
/* Selector carries the parent class deliberately: css/style.css has
   ".signs-master-chief > div{width:30%;text-align:center}" and
   ".master-chief{align-items:center}", both of which outrank a bare
   ".od-sign" and were squeezing each signature into a centred 30%-wide
   column - which is what made the names wrap a word per line. */
.signs-master-chief > .od-sign{
	display: flex !important; flex-direction: column; gap: 8px;
	width: auto !important; text-align: left !important;
	align-items: stretch !important; justify-content: flex-start !important;
	padding: 0 !important; height: auto !important; font-size: inherit !important;
}
.od-sign-line{
	height: 52px; border-bottom: 1px solid #C2CCCB;
	display: flex; align-items: flex-end; justify-content: flex-start; overflow: hidden;
}
/* Sits ON the line, like a signed form. Capped so an oversized scan can't
   stretch the row. */
.od-sign-line img.written-sign{
	max-height: 50px; max-width: 100%; width: auto; object-fit: contain; display: block;
}
.od-sign-role{
	font-family: var(--od-mono); font-size: 11px; color: var(--od-faint);
	text-transform: uppercase; letter-spacing: .02em;
}
.od-sign-name.signer-name{
	font-size: 13px !important; font-weight: 600 !important; letter-spacing: .01em !important;
	text-transform: uppercase; margin: 0 !important; color: var(--od-ink);
}

/* ---- Item table ----
   Bootstrap's .table-striped/.table-bordered classes are still on the table
   (other code and the print popup key off them), so their zebra fill and
   full grid are overridden here rather than removed from the markup. */
.od-card .table-responsive{ margin: 0; max-width: 100%; }
.od-card table.orderedItemTable{ font-size: 13px; margin: 0; border: none; }
.od-card table.orderedItemTable td,
.od-card table.orderedItemTable th{ border-left: none !important; border-right: none !important; }
.od-card table.orderedItemTable tbody tr:nth-of-type(odd){ background: transparent; }
.od-card table.orderedItemTable thead th{
	background: var(--od-surface); border-bottom: 1px solid var(--od-line) !important;
	border-top: none !important; font-size: 11.5px; font-weight: 600; letter-spacing: 0;
	text-transform: none; color: #4C5958; padding: 10px 12px; white-space: nowrap;
	vertical-align: bottom;
}
/* The grouping row above the real headers: Item / Stock & supply /
   Quantities, as separated bands rather than another row of labels. */
.od-card table.orderedItemTable thead tr.od-thead-group th{
	background: var(--od-ground); color: var(--od-muted-2);
	font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
	padding: 8px 12px; border-bottom: 1px solid #E7ECEB !important;
}
.od-card table.orderedItemTable thead tr.od-thead-group th + th{ border-left: 1px solid #E7ECEB !important; }
.od-card table.orderedItemTable thead th.num{ text-align: right; }
.od-card table.orderedItemTable tbody td{
	padding: 13px 12px; border-bottom: 1px solid #F0F3F2 !important;
	border-top: none !important; vertical-align: top; color: var(--od-ink-soft);
}
.od-card table.orderedItemTable tbody tr:hover{ background: #FAFCFB; }
.od-card table.orderedItemTable td.num{ font-family: var(--od-mono); text-align: right; font-size: 12.5px; }
.od-card table.orderedItemTable td.muted{ color: var(--od-faint); }
.od-card table.orderedItemTable .serial{ font-family: var(--od-mono); font-weight: 400; font-size: 12.5px; color: var(--od-muted); }
.od-card table.orderedItemTable td:nth-child(2){ font-family: var(--od-mono); font-size: 12.5px; color: var(--od-faint); }
.od-card table.orderedItemTable td.item-name-td{ font-size: 13.5px; color: var(--od-ink); min-width: 240px; }
.od-card table.orderedItemTable td.item-name-td .see-attachments-link{ font-size: 12px; padding: 0; }
.od-card table.orderedItemTable td.item-unit span,
.od-card table.orderedItemTable td.item-unit{
	font-family: var(--od-mono); font-size: 12px;
}
.od-card table.orderedItemTable td.req_qty, .od-card table.orderedItemTable td.deliver_qty,
.od-card table.orderedItemTable td.rcv_qty, .od-card table.orderedItemTable td.invoice_qty,
.od-card table.orderedItemTable td.unit_price, .od-card table.orderedItemTable td.line_total{
	font-family: var(--od-mono); text-align: right; font-size: 12.5px;
}
.od-card table.orderedItemTable input.form-control{
	font-family: var(--od-mono); text-align: right; font-size: 13px;
	border: 1px solid #D3DBDA; border-radius: 7px; padding: 7px 9px;
}
.od-card table.orderedItemTable input.form-control:focus{
	border-color: var(--od-accent); box-shadow: 0 0 0 3px rgba(14,124,116,.12); outline: none;
}
</style>
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="od-header">
					<div>
						<div class="od-eyebrow">Requested Order Details</div>
						<h1 class="od-title">{{!empty($order->vessel->name)?$order->vessel->name:''}}</h1>
					</div>
					{{-- The "Call for Tender / Placed Work Order / Supplied to Ship"
						 dropdown that used to sit here was the ancestor of the
						 procurement workflow: it wrote unvalidated text straight
						 over orders.status, which now also carries the approval
						 chain's own state. Replaced by the stage panel further
						 down (see App\ProcurementStage). --}}
					@php
						$currentRole = auth()->user()->role->role ?? null;
						// Only offer an action to someone who actually still has
						// one here - previously every role saw Approve on every
						// order forever, including ones they'd already signed off.
						$canAct = $order->hasPendingActionFor($currentRole, auth()->id());
						// Once GM has delegated AND that reviewer has actually sent
						// it back, there's nothing left to delegate - GM just
						// approves it. Before that (no delegation yet, or one still
						// out for review) the delegate option stays available
						// alongside Approve, since GM can still choose either.
						$srdDelegateReviewed = $currentRole == 'gm-srd' && (
							!empty($order->orderApproval->dgm_srd_app)
							|| !empty($order->orderApproval->agm_app)
							|| !empty($order->orderApproval->ast_m_app)
							|| !empty($order->orderApproval->superintendent_srd_app)
						);

						// --- SSM procurement workflow ---------------------
						$procStage = $order->procurement_stage;
						$inProcurement = $order->inProcurement();
						// Delivery and Receipt & Verification are taken through
						// the Approve button below; every other stage has its
						// own panel further down the page.
						$isApprovalStage = \App\ProcurementStage::isApprovalFlowStage($procStage);
						$canWorkStage = $canAct && $inProcurement && ! $isApprovalStage
							&& \App\ProcurementStage::owner($procStage) === \App\ProcurementStage::OWNER_SSM;
						$atInvoiceStage = $canWorkStage && \App\ProcurementStage::capturesInvoice($procStage);

						// Pricing is SSM-side only. Gated on user_type rather
						// than listing the ship roles, so a new ship role can't
						// accidentally inherit price visibility.
						$userType = auth()->user()->role->user_type ?? null;
						$showPrices = $userType !== 'ship';
						$hasPricing = $order->orderItems->contains(fn($line) => $line->unit_price !== null);

						// The procurement trail is the SSM department's own
						// working record - who ran the tender, what the bids
						// were, when finance cleared it. Only the SSM end sees
						// it; the ship and SRD sides get the current stage from
						// the Stage pill and the panel, without the internals.
						// user_type 'ssm' covers DGM/AGM/AM (SSM), so a new SSM
						// role picks this up without another edit here.
						$showProcurementTrail = $userType === 'ssm';

						// DGM (SSM) assigns rather than approves, and an SSM
						// officer only approves at the Delivery stage.
						$showApproveButton = $canAct
							&& $currentRole != 'operator'
							&& $currentRole != 'dgm-ssm'
							&& ($inProcurement ? $isApprovalStage : $currentRole != 'am-ssm');

						$approveLabel = 'Approve';
						if ($procStage === \App\ProcurementStage::DELIVERY) {
							$approveLabel = 'Confirm Delivery';
						} elseif ($procStage === \App\ProcurementStage::RECEIPT_VERIFICATION
							|| ($currentRole == 'master' && $order->status == 'delivered')) {
							$approveLabel = 'Confirm Receipt';
						}

						// The acknowledgement receipt upload lives in the
						// Receipt & Verification panel further down the page,
						// easy to miss above the fold - only relevant when
						// that panel actually exists (the procurement version
						// of this stage, not the legacy pre-procurement path).
						$showReceiptUploadReminder = $showApproveButton
							&& $inProcurement
							&& $currentRole == 'master'
							&& $procStage === \App\ProcurementStage::RECEIPT_VERIFICATION;
					@endphp
					<div class="od-actions">
						@if($showApproveButton)
						<button type="button" class="btn btn-primary" id="approve_order" data-id="{{$order->id}}">
							<i class="fas fa-check-circle"></i> {{ $approveLabel }}
						</button>
						@endif

						{{-- DGM (SSM) doesn't approve - they assign it to one named
						     SSM officer, who then takes the final action. --}}
						@if($canAct && $currentRole == 'dgm-ssm')
						<select class="form-control" id="ssm_assignee">
							<option value="">Assign to…</option>
							@foreach($ssmOfficers ?? [] as $roleName => $officers)
							<optgroup label="{{ strtoupper(str_replace('-', ' ', $roleName)) }}">
								@foreach($officers as $officer)
								<option value="{{ $officer->user->id }}">{{ $officer->user->name }}</option>
								@endforeach
							</optgroup>
							@endforeach
						</select>
						<button type="button" class="btn btn-primary" id="assign_ssm" data-id="{{$order->id}}"><i class="fas fa-user-plus"></i> Assign</button>
						@endif

						{{-- GM (SRD) delegates to one named reviewer - DGM/AGM/AM/
						     Superintendent (SRD) can each be more than one real
						     person, so picking the role alone isn't enough. Hidden
						     once a delegate has already reviewed and sent it back -
						     at that point GM just approves, above. --}}
						@if($canAct && $currentRole == 'gm-srd' && !$srdDelegateReviewed)
						<select class="form-control" id="srd_delegate_target">
							<option value="">Delegate to…</option>
							@foreach($srdOfficers ?? [] as $roleName => $officers)
							<optgroup label="{{ strtoupper(str_replace('-', ' ', $roleName)) }}">
								@foreach($officers as $officer)
								<option value="{{ $officer->user->id }}">{{ $officer->user->name }}</option>
								@endforeach
							</optgroup>
							@endforeach
						</select>
						<button type="button" class="btn btn-info" id="forward_toagm" data-id="{{$order->id}}"><i class="fas fa-angle-double-right"></i> Forward</button>
						@endif
						<button type="button" class="btn btn-info btn-bvprint print-order-details"><i class="fa fa-print"></i> Print</button>
					</div>
					@if($showReceiptUploadReminder)
					<p class="od-receipt-reminder">
						Please add signed acknowledgement receipt in
						<a href="#procurement-panel">Receipt &amp; Verification</a> before confirming.
					</p>
					@endif
				</div>
				<div class="card-body">
					{{-- On-screen only - #order-print-header2 further down carries
						 the same four fields into the print copy, in the shape
						 print-pdf-custom.js expects. Kept separate rather than
						 restyled in place, since that element's outerHTML is
						 copied verbatim into the print popup. --}}
					<div class="od-infobar">
						<div>
							<div class="k">Req. No.</div>
							<div class="v mono">{{ $order->req_no ?: '—' }}</div>
						</div>
						<div>
							<div class="k">Date</div>
							<div class="v mono">{{ $order->req_date }}</div>
						</div>
						<div>
							<div class="k">Port</div>
							<div class="v">{{ $order->port_name }}</div>
						</div>
						<div>
							<div class="k">Stage</div>
							<div class="od-stage-pill {{ $order->procurementClosed() || $order->status === 'received' ? 'is-done' : '' }}">
								<span class="dot"></span>{{ $order->currentStageLabel() }}
							</div>
						</div>
					</div>
					@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null))
					<form class="form mb-3" id="deliveredQtyForm">
						@csrf
						@endif
						{{-- This table now runs Item No./IMPA/Item Name/Unit/Opening Stock/
							 Last Supply/In Stock/Total Supply/Req Qty/Attachments/Deliverd
							 Qty/Rcv Qty(/Action) - forcing that to 100% width squeezes every
							 header and pushes the whole page wider than the viewport. Scroll
							 horizontally inside this box instead, never the page itself. --}}
						<div class="od-card">
						<div class="od-card-head"><h2>Requisition Items</h2></div>
						<div class="table-responsive">
						<table id="example" class="table table-striped table-bordered orderedItemTable OrderDetailsTable" style="width:auto; min-width:100%;">
							<div class="row mb-3 justify-content-between" id="order-print-header2">
								<div class="col">
									<strong>Vessel:</strong> {{$order->vessel->name}}
								</div>
								<div class="col">
									<strong>Req. No:</strong> {{$order->req_no}}
								</div>
								<div class="col">
									<strong>Date:</strong> {{$order->req_date}}
								</div>
								<div class="col">
									<strong>Port:</strong> {{$order->port_name}}
								</div>
								<div class="col">
									<strong>Stage:</strong> <span class="badge badge-info">{{ $order->currentStageLabel() }}</span>
								</div>
							</div>
							@php
								// Whether the invoice columns and the Action column are in
								// play, so the grouping row's colspans add up to the real
								// column count - a short grouping row throws the whole
								// table's alignment out.
								$showInvoiceCols = $showPrices && ($atInvoiceStage || $hasPricing);
								$showActionCol = (auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship')
									|| (auth()->user()->role->role=='operator' && $order->status=='delivered')
									|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null);
							@endphp
							<thead>
								{{-- Grouping row. DataTables maps columns off the LAST
									 thead row, so this one is presentation only. --}}
								<tr class="od-thead-group">
									<th colspan="4">Item</th>
									<th colspan="4">Stock &amp; supply</th>
									<th colspan="3">Quantities</th>
									@if($showInvoiceCols)<th colspan="3">Invoice</th>@endif
									@if($showActionCol)<th></th>@endif
								</tr>
								<tr>
									<th>No.</th>
									<th>IMPA</th>
									<th>Item Name
										<!-- <span class="item-name">Item Name</span> -->
										<!-- <span class="item-name-print">Description <br> As per IMPA Code 6Th Edn</span> -->
									</th>
									<th>Unit</th>
									<th class="num">Opening</th>
									<th class="num">Last supply</th>
									<th class="num">In stock</th>
									<th class="num">Total supply</th>
									<th class="num">Req</th>
									<th class="num">Delivered</th>
									<th class="num">Rcv Qty</th>
									{{-- Invoice columns are SSM-side only, and only once
										 there is something to show (or something to enter). --}}
									@if($showInvoiceCols)
									<th class="num">Invoice Qty</th>
									<th class="num">Unit Price</th>
									<th class="num">Line Total</th>
									@endif
									<!-- <th class="item-cat">Category</th> -->
									@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null)) 
									<th class="">Action</th>
									@endif
								</tr>
							</thead>
							<tbody>
								@if(!empty($order->orderItems))
								@foreach($order->orderItems as $orderItem)
								<tr>
									<td><b class="serial">{{$loop->iteration}}</b></td>
									<td>{{$orderItem->item->impa_code}}</td>
									<td class="item-name-td">
										{{$orderItem->item->name}}
										@if($orderItem->attachments->isNotEmpty())
										<br>
										<button type="button" class="btn btn-link p-0 see-attachments-link" data-toggle="modal" data-target="#view-attachments-modal"
											data-item-name="{{ $orderItem->item->name }}"
											data-attachments="{{ $orderItem->attachments->map(fn($a) => ['title'=>$a->title,'kind'=>$a->kind,'uploaded_by'=>$a->uploader->name ?? '','uploaded_at'=>optional($a->pivot->created_at)->diffForHumans() ?? $a->created_at->diffForHumans(),'view_url'=>url('/attachments/'.$a->id.'/view')])->toJson() }}"
											style="font-size:12px;">
											<i class="fas fa-paperclip"></i> See attachments ({{ $orderItem->attachments->count() }})
										</button>
										@endif
									</td>
									<td class="item-unit">{{$orderItem->item->unit}}</td>
									{{-- Opening Stock and Last Supply are the figures captured when this
										 requisition was raised, so an old form still prints what justified
										 it. In Stock is live - what is on board right now. --}}
									<td class="num muted">{{ $orderItem->opening_stock !== null ? $orderItem->opening_stock : '—' }}</td>
									<td class="num muted">
										@if($orderItem->last_supply_qty !== null)
										{{ $orderItem->last_supply_qty }}
										@if($orderItem->last_supply_date)
										<br><span class="text-muted" style="font-size:11px;">{{ \Carbon\Carbon::parse($orderItem->last_supply_date)->format('d M Y') }}</span>
										@endif
										@else
										—
										@endif
									</td>
									<td class="num">{{ $liveStock[$orderItem->item_id]['stock_qty'] ?? 0 }}</td>
									<td class="num muted">{{ $totalSupplied[$orderItem->item_id] ?? 0 }}</td>
									<td class='req_qty'>
										{{-- Master/Chief Engineer can correct the deck/engine officer's
											 requested quantity at their own review turn, before
											 forwarding it ashore - excluding Master's receipt-confirmation
											 turn (status 'delivered'), where the original ask is no longer
											 what's being acted on. --}}
										@if(auth()->user()->role->role=='am-srd' && $order->ast_m_app==null)
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control req-qty" name="req_qty[{{$orderItem->id}}]" value="{{$orderItem->item_qty}}">
										</div>
										@elseif($canAct && $currentRole=='chief-engineer')
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control req-qty" name="req_qty[{{$orderItem->id}}]" value="{{$orderItem->item_qty}}">
										</div>
										@elseif($canAct && $currentRole=='master' && $order->status != 'delivered')
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control req-qty" name="req_qty[{{$orderItem->id}}]" value="{{$orderItem->item_qty}}">
										</div>
										@else
										{{$orderItem->item_qty}}
										@endif
									</td>
									<td class='deliver_qty'>
										{{-- Only at the Delivery stage itself. In procurement the SSM
											 officer has an action at ten different stages, and Deliver
											 Qty is only meaningful at one of them. --}}
										@if($canAct && in_array($currentRole, ['agm-ssm', 'am-ssm', 'superintendent-ssm'])
											&& (! $inProcurement || $procStage === \App\ProcurementStage::DELIVERY))
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control deliver-qty" name="deliver_qty[{{$orderItem->id}}]" value="{{ $orderItem->del_item_qty ?? $orderItem->item_qty }}">
										</div>
										@else
										{{!empty($orderItem->del_item_qty)?$orderItem->del_item_qty:''}}
										@endif
									</td>
									<td class='rcv_qty'>
										@if($canAct && $currentRole == 'master' && $order->status == 'delivered')
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control rcv-qty" name="rcv_qty[{{$orderItem->id}}]" value="{{ $orderItem->rcv_item_qty ?? $orderItem->del_item_qty }}">
										</div>
										@else
										{{!empty($orderItem->rcv_item_qty)?$orderItem->rcv_item_qty:''}}
										@endif
									</td>
									@if($showPrices && ($atInvoiceStage || $hasPricing))
									<td class='invoice_qty'>
										@if($atInvoiceStage)
										<div class="form-group" style="margin: 0">
											{{-- Defaults to what the Master actually confirmed on board,
												 so billing for more than arrived is a visible edit
												 rather than the path of least resistance. --}}
											<input type="number" min="0" data-id="{{$orderItem->id}}" class="form-control invoice-qty"
												name="invoice_qty[{{$orderItem->id}}]"
												value="{{ $orderItem->invoice_qty ?? $orderItem->rcv_item_qty ?? $orderItem->del_item_qty }}">
										</div>
										@else
										{{ $orderItem->invoice_qty }}
										@endif
									</td>
									<td class='unit_price'>
										@if($atInvoiceStage)
										<div class="form-group" style="margin: 0">
											<input type="number" step="0.01" min="0" data-id="{{$orderItem->id}}" class="form-control unit-price"
												name="unit_price[{{$orderItem->id}}]" value="{{ $orderItem->unit_price }}">
										</div>
										@else
										{{ $orderItem->unit_price !== null ? number_format($orderItem->unit_price, 2) : '' }}
										@endif
									</td>
									<td class='line_total' data-id="{{$orderItem->id}}">
										{{ $orderItem->line_total !== null ? number_format($orderItem->line_total, 2) : '' }}
									</td>
									@endif

									@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null))
									<td class="action">
										<button class="btn btn-info" id="indSave" data-role="{{auth()->user()->role->role}}"> Save</button>
									</td>
									@endif
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
						</div>
						</div>
						@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null))
						<input type="hidden" class="form-control" value="{{$order->id}}" name="orderId">
						<button class="btn btn-info float-right mt-2" type="submit"> Save All </button>
					</form>
					@endif

					{{-- Reason is originally stated by whoever raises the requisition
						 (the wizard requires it before this page is ever reached; for a
						 pre-wizard order this manual textarea is where it gets set).
						 Master/Chief Engineer can refine it at their own review turn
						 once the officer has actually written one - correcting wording
						 before it goes ashore, not writing it from scratch.

						 Its own card BELOW the items table. It used to sit literally
						 inside <table>, before <thead>, which is invalid: browsers
						 foster-parent stray table children out to just before the
						 table, so it rendered jammed into the top of the items card. --}}
					@php
						$canEditReason = (auth()->user()->role->role == 'chief-officer' && empty($order->orderApproval->cheif_ofcr_app))
							|| (auth()->user()->role->role == 'second-engineer' && empty($order->orderApproval->second_eng_app))
							|| ($canAct && $currentRole == 'chief-engineer' && !empty($order->reason))
							|| ($canAct && $currentRole == 'master' && $order->status != 'delivered' && !empty($order->reason));
					@endphp
					@if($canEditReason)
					<div class="od-card od-reason-card">
						<div class="od-card-body">
							<label class="od-reason-label" for="requisition_reason">Reason of Requisition</label>
							<textarea class="form-control" id="requisition_reason" rows="3" placeholder="Why is this requisition needed?">{{ $order->reason }}</textarea>
						</div>
					</div>
					@elseif(!empty($order->reason))
					<div class="od-card od-reason-card">
						<div class="od-card-body">
							<div class="od-reason-label">Reason of Requisition</div>
							<div class="od-reason-text">{{ $order->reason }}</div>
						</div>
					</div>
					@endif

					{{-- SSM procurement workflow. The panel is the action for
						 whichever stage is current; the timeline below it is
						 everything already done.

						 Sits ABOVE the rule below, not between it and the
						 signatures: .signs-master-chief carries margin-top:-45px
						 (css/style.css), so whatever directly precedes the
						 signature block gets 45px of it painted over. That used
						 to be the blank space here, which absorbed it harmlessly. --}}
					@if($inProcurement)
					@php
						// Colour-codes the panel and the button by which of the
						// four broad phases the current stage belongs to -
						// Sourcing/Handover/Finance/Closed - so a glance at the
						// header colour says roughly where in procurement this
						// requisition sits, before reading a word of text.
						$procCategory = \App\ProcurementStage::category($order->procurementClosed() ? \App\ProcurementStage::CLOSED : $procStage);

						// The trail shows the WHOLE sequence, not just what's
						// already happened - done stages, the one that's current
						// (highlighted), and what's still ahead (hollow), so the
						// viewer can see how much is left without reading the
						// twelve-stage list from memory. Completed rows carry
						// their real recorded data; current/pending rows are
						// synthesized here since they have no step row yet.
						$completedBySlug = $order->procurementSteps->keyBy('step');
						$trail = [];
						foreach (\App\ProcurementStage::SEQUENCE as $slug) {
							if ($slug === \App\ProcurementStage::CLOSED) {
								continue;
							}
							if ($step = $completedBySlug->get($slug)) {
								$trail[] = ['step' => $step, 'slug' => $slug, 'state' => $step->wasSkipped() ? 'skipped' : 'done'];
							} elseif ($slug === $procStage && ! $order->procurementClosed()) {
								$trail[] = ['step' => null, 'slug' => $slug, 'state' => 'current'];
							} else {
								$trail[] = ['step' => null, 'slug' => $slug, 'state' => 'pending'];
							}
						}
						$trailDone = collect($trail)->whereIn('state', ['done', 'skipped'])->count();
					@endphp
					<div class="procurement-block proc-cat-{{ $procCategory }}" id="procurement-panel">
						<div class="proc-head">
							<div>
								<div class="proc-eyebrow">
									Procurement
									<span class="proc-phase-tag">{{ \App\ProcurementStage::CATEGORY_LABELS[$procCategory] }}</span>
								</div>
								<div class="proc-stage-name">
									{{ $order->procurementClosed() ? 'Closed' : \App\ProcurementStage::label($procStage) }}
								</div>
							</div>
						</div>

						@if($canWorkStage)
						<form id="procurement-step-form" class="proc-form">
							<input type="hidden" id="proc-stage" value="{{ $procStage }}">

							@if($atInvoiceStage)
							<div class="proc-fields">
								<div class="proc-field">
									<label for="invoice_no">Invoice No.</label>
									<input type="text" class="form-control" id="invoice_no">
								</div>
								<div class="proc-field">
									<label for="invoice_date">Invoice Date</label>
									<input type="date" class="form-control" id="invoice_date">
								</div>
								<div class="proc-field">
									<label for="invoice_discount">Discount (BDT)</label>
									<input type="number" step="0.01" min="0" class="form-control" id="invoice_discount" value="0">
								</div>
							</div>
							<div class="proc-totals">
								<div><span>Subtotal</span><strong id="inv-subtotal">0.00</strong></div>
								<div><span>Discount</span><strong id="inv-discount">0.00</strong></div>
								<div class="payable"><span>Payable</span><strong id="inv-payable">0.00</strong></div>
							</div>
							@endif

							@if(\App\ProcurementStage::metaFields($procStage))
							<div class="proc-fields">
								@foreach(\App\ProcurementStage::metaFields($procStage) as $field)
								@php
									$metaType = str_contains($field, '_date') ? 'date'
										: (str_contains($field, 'amount') || in_array($field, ['vendors_invited', 'bids_received']) ? 'number' : 'text');
								@endphp
								<div class="proc-field">
									<label for="meta_{{ $field }}">{{ \App\ProcurementStage::metaLabel($field) }}</label>
									<input type="{{ $metaType }}" @if($metaType === 'number') step="0.01" min="0" @endif
										class="form-control proc-meta" id="meta_{{ $field }}" data-field="{{ $field }}">
								</div>
								@endforeach
							</div>
							@endif

							<div class="proc-two-col">
								<div class="proc-field">
									<label for="proc_remarks">Remarks <span class="opt">optional</span></label>
									<textarea class="form-control" id="proc_remarks" rows="4"></textarea>
								</div>
								@include('partials.procurement-attachments', ['stageLabel' => \App\ProcurementStage::label($procStage)])
							</div>

							<div class="proc-actions">
								@if(\App\ProcurementStage::isSkippable($procStage))
								<button type="button" class="btn btn-secondary" id="proc-skip" data-id="{{$order->id}}">
									Not required
								</button>
								@endif
								<button type="button" class="btn proc-btn-complete" id="proc-complete" data-id="{{$order->id}}">
									<i class="fas fa-check-circle"></i>
									Complete {{ \App\ProcurementStage::label($procStage) }}
								</button>
							</div>
						</form>
						@elseif($canAct && $currentRole == 'master' && $procStage === \App\ProcurementStage::RECEIPT_VERIFICATION)
						{{-- Receipt & Verification is taken through the Confirm
							 Receipt button at the top of the page (it credits stock
							 and writes the received quantities); these two fields
							 ride along with that same click. --}}
						<p class="proc-hint">
							Check the quantities that actually arrived against Rcv Qty in the table above,
							then use <strong>Confirm Receipt</strong>. Attach the signed acknowledgement
							receipt here first — you can add more than one.
						</p>
						<div class="proc-two-col">
							<div class="proc-field">
								<label for="rcv_remarks">Remarks <span class="opt">optional</span></label>
								<textarea class="form-control" id="rcv_remarks" rows="4" placeholder="e.g. 2 units short"></textarea>
							</div>
							@include('partials.procurement-attachments', ['stageLabel' => 'Acknowledgement receipt'])
						</div>
						@elseif(! $order->procurementClosed())
						<p class="proc-waiting">
							@if(\App\ProcurementStage::owner($procStage) === \App\ProcurementStage::OWNER_SHIP)
							Waiting on the vessel to confirm receipt of the delivery.
							@else
							Waiting on {{ optional(\App\User::find($order->orderApproval->assigned_to_ssm))->name ?? 'the assigned SSM officer' }}.
							@endif
						</p>
						@endif

						@if($showPrices && $order->invoice)
						<div class="proc-invoice-summary">
							<div><span>Invoice</span><strong>{{ $order->invoice->invoice_no ?: '—' }}</strong></div>
							<div><span>Subtotal</span><strong>{{ number_format($order->orderItems->sum('line_total'), 2) }}</strong></div>
							<div><span>Discount</span><strong>{{ number_format($order->invoice->discount, 2) }}</strong></div>
							<div class="payable"><span>Payable</span><strong>{{ number_format($order->invoice->payable, 2) }}</strong></div>
						</div>
						@endif
					</div>
					@endif

					{{-- The trail is its own card, below the stage panel: one is
						 the action you take now, the other is the record of what
						 has happened - different things to look at. SSM-side only
						 (see $showProcurementTrail). --}}
					@if($showProcurementTrail && $inProcurement && !empty($trail))
					<div class="od-card od-trail-card">
						<div class="od-card-body">
						<div class="proc-trail-head">
							<h2>Procurement trail</h2>
							<span class="proc-trail-progress">{{ $trailDone }} of {{ count($trail) }} complete</span>
						</div>
						<ol class="proc-timeline">
							@foreach($trail as $row)
							@php $step = $row['step']; @endphp
							<li class="state-{{ $row['state'] }}">
								<div class="t-rail"><span class="t-dot"></span><span class="t-line"></span></div>
								<div class="t-body">
									<div class="t-head">
										<span class="t-name">{{ \App\ProcurementStage::label($row['slug']) }}</span>
										@if($row['state'] === 'skipped')<span class="t-badge">not required</span>@endif
										@if($step)<span class="t-when">{{ optional($step->completed_at)->format('d M Y') }}</span>@endif
									</div>
									@if($step)
									<div class="t-who">{{ optional($step->completedBy)->name }}</div>
									@if($step->remarks)
									<div class="t-remarks">{{ $step->remarks }}</div>
									@endif
									@if($step->meta)
									<div class="t-meta">
										@foreach($step->meta as $key => $value)
										@if($key !== 'required')
										<span><em>{{ \App\ProcurementStage::metaLabel($key) }}:</em> {{ $value }}</span>
										@endif
										@endforeach
									</div>
									@endif
									@if($step->attachments->isNotEmpty())
									<div class="t-docs">
										@foreach($step->attachments as $doc)
										<a href="{{ url('/attachments/'.$doc->id.'/view') }}" target="_blank" rel="noopener" class="t-doc">
											<i class="fas fa-paperclip"></i> {{ $doc->title }}
										</a>
										@endforeach
									</div>
									@endif
									@elseif($row['state'] === 'current')
									<div class="t-who">In progress</div>
									@endif
								</div>
							</li>
							@endforeach
						</ol>
						</div>
					</div>
					@endif

					{{-- Signatures, redesigned as a clean line + role + name grid
						 (no signature image) rather than the old cursive-image
						 layout. The wrapping classes (print-header,
						 signs-master-chief, master-chief, signer-name) stay
						 exactly as they were: print-pdf-custom.js copies this
						 element's outerHTML verbatim into a popup window that
						 carries its OWN stylesheet targeting those same class
						 names, so removing them would leave the printed copy
						 unstyled even though nothing here would show it. --}}
					<div class="od-card od-auth-card">
						<div class="od-card-body">
							<div class="od-reason-label">Authorisation</div>
							<div id="order-print-footer1" class="print-header signs-master-chief">
							    @foreach(\App\Role::orderBy('user_type','asc')->get() as $role)
									@if($role->user->id==$order->orderApproval->master_app
									|| $role->user->id==$order->orderApproval->chief_eng_app
									|| $role->user->id==$order->orderApproval->cheif_ofcr_app
									|| $role->user->id==$order->orderApproval->second_eng_app
									|| $role->user->id==$order->orderApproval->ast_m_app
									|| $role->user->id==$order->orderApproval->agm_app
									|| $role->user->id==$order->orderApproval->gm_app
									|| $role->user->id==$order->orderApproval->dgm_app_ssm
									|| $role->user->id==$order->orderApproval->agm_app_ssm
									|| $role->user->id==$order->orderApproval->am_app_ssm
										)
										@php
											// Only render the signature when the file is really
											// on disk. The original markup pointed <img> at
											// url('/'.$sign) unconditionally, so a signer with
											// no signature on file - or one whose file has since
											// gone missing - produced a broken-image icon rather
											// than a blank signing line. Both cases exist in the
											// current data.
											$signPath = $role->user->sign;
											$hasSign = !empty($signPath) && file_exists(base_path($signPath));
										@endphp
										<div class="master-chief od-sign">
									<div class="od-sign-line">
										@if($hasSign)
										<img class="written-sign sign" src="{{ url('/'.$signPath) }}" alt="Signature of {{ $role->user->name }}">
										@endif
									</div>
									<div class="od-sign-role">{{$role->role}}</div>
									<div class="signer-name od-sign-name">{{$role->user->name}}</div>
										</div>
										@endif
								@endforeach
							</div>
						</div>
					</div>

					<!-- sign section for bsc admin -->
					<div id="order-print-footer2" class="print-header" hidden>
						<div class="signs-master-chief-admin">
							<div class="master-chief">
								<span class="written-sign sign"><img src="{{asset('/images/master-sign.jpg')}}" alt=""></span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>Master/Chief Engineer</span>
								<span class="signer-name">Md. Rabiul Chowdhury</span>
								<span class="seal-master-chief seal"><img src="{{asset('/images/master.jpg')}}" alt=""></span>
							</div>
							<div class="chief-officer">
								<span class="written-sign sign"><img src="{{asset('/images/chief-sign.jpg')}}" alt=""></span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>Asistant General Manager</span>
								<span class="signer-name">Md. Rabiul Hasan</span>
								<span class="seal-chief-officer seal"><img src="{{asset('/images/chief-officer-seal.jpg')}}" alt=""></span>
							</div>
							<div class="chief-officer">
								<span class="written-sign sign"><img src="{{asset('/images/chief-sign.jpg')}}" alt=""></span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>General Manager</span>
								<span class="signer-name">Md. Hasan Chowdhury</span>
								<span class="seal-chief-officer seal"><img src="{{asset('/images/chief-officer-seal.jpg')}}" alt=""></span>
							</div>
						</div>
					</div>
				</div> 
			</div>                               
		</div>
	</div>
</div>

<!-- print header -->
<div id="order-print-header1" class="print-header" >
	<div class="title-wrap od-title">
		<div class="logo">
			<a href="{{url('/')}}"><img src="{{asset('/images/logo.png')}}" alt="Site Logo"></a>
		</div>
		<div class="title-center">
			<h2 class="line1">SDD/SMM/Receipt Note/Ship's Copy</h2>
			<h2 class="line2">Bangladesh Shipping Corporation</h2>
			<h2 class="line3">Ship <span></span> Repair <span></span> Department</h2>
			<h3 class="line4x"><span class="req_cat">{{!empty($order->orderItems[0]->item->category->name)?$order->orderItems[0]->item->category->name:''}}</span> Requitition</h3>
		</div>
		<div class="title-right">
		</div>			
	</div>
</div>
<div id="order-print-header3" class="print-header" >
	<table class="office-use-table">
		<body>
			<tr>
				<td colspan="2" class="office_use">Office Use</td>
				<td colspan="3" class="office_use_form">
					<p>1 Checked by <span class="checked_by"></span> Date <span class="date"></span> Passed to SSM Dept. on <span class="passed"></span></p>
					<p>2 Invitation to Tender sent on <span class="invitation"></span> Tenders received on <span class="tender_rdate"></span></p>
					<p>3 Order approved on <span class="approved_date"></span> Supply order issued on <span class="soi_date"></span></p>
					<p>4 Delivered on board on <span class="delevered_obdate"> </span> Delivery complete/incomplete <span class="dci_date"></span></p>
					<p>5 Bill received on <span class="bil_rdate"></span> Put up for approval on <span class="pua_date"></span> passed for payment on <span class="pfp_date"></span></p>
				</td>
			</tr>
		</body>
	</table>
</div>
<!-- ./print header -->

<!-- See Attachments modal: view-only list for whoever is reviewing this
	 requisition. Populated straight from the clicked link's data-attachments
	 attribute (rendered server-side above) - no round trip needed just to
	 look at what's already on the page. -->
<div class="modal fade" id="view-attachments-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document" style="max-width:460px;">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<div style="font-size:11.5px;color:#6b7a82;font-weight:600;margin-bottom:2px;">Attachments for</div>
					<h5 class="modal-title" id="view-attachments-item-name">&nbsp;</h5>
				</div>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body" id="view-attachments-list" style="display:flex; flex-direction:column; gap:8px;"></div>
		</div>
	</div>
</div>
<style>
	/* --- SSM procurement workflow panel + timeline ---
	   Colour-coded by broad phase (App\ProcurementStage::category()) rather
	   than one flat colour for all twelve stages or a different one for
	   each - Sourcing/Handover/Finance/Closed each get a hue, carried by
	   these four custom-property sets and picked up by the eyebrow tag,
	   the stage-count pill, the accent bar and the Complete button. */
	.procurement-block.proc-cat-sourcing{ --proc-c:#0E7C74; --proc-bg:#EAF6F4; --proc-c-dark:#0A5D57; }
	.procurement-block.proc-cat-handoff{ --proc-c:#C2700C; --proc-bg:#FFF4E5; --proc-c-dark:#8A4B08; }
	.procurement-block.proc-cat-finance{ --proc-c:#6a3fb0; --proc-bg:#efe6fa; --proc-c-dark:#54318c; }
	.procurement-block.proc-cat-closed{ --proc-c:#0E7C74; --proc-bg:#EAF6F4; --proc-c-dark:#0A5D57; }

	.procurement-block{
		border:1px solid var(--od-line, #DFE5E4); border-radius:var(--od-radius, 12px);
		background:var(--od-surface, #fff); padding:20px; margin:0 0 20px;
		display:flex; flex-direction:column; gap:18px;
		/* The signature block below pulls itself up 45px and paints an opaque
		   white background over whatever it lands on, so keep clear of it. */
		position:relative; z-index:1;
	}
	.proc-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
	.proc-eyebrow{ font-size:10.5px; letter-spacing:.12em; text-transform:uppercase; color:var(--od-muted-2, #778483); font-weight:600; display:flex; align-items:center; gap:10px; }
	.proc-phase-tag{
		font-size:10.5px; letter-spacing:.06em; font-weight:600; text-transform:uppercase;
		color:var(--proc-c-dark, #4a5c63); background:var(--proc-bg, #eef2f2); padding:3px 8px; border-radius:5px;
	}
	.proc-stage-name{ font-size:17px; font-weight:600; color:var(--od-ink, #17242b); margin-top:6px; }
	.proc-hint{ font-size:13.5px; line-height:1.6; color:var(--od-muted, #4C5958); margin:0; max-width:62ch; }
	.proc-waiting{ font-size:13.5px; color:var(--od-muted, #6b7a82); margin:0; font-style:italic; }
	.proc-form{ display:flex; flex-direction:column; gap:18px; }
	.proc-fields{ display:flex; flex-wrap:wrap; gap:14px; }
	.proc-field{ flex:1 1 200px; min-width:0; }
	.proc-field.full{ flex-basis:100%; }
	.proc-field label{ display:block; font-size:12px; font-weight:600; color:var(--od-ink-soft, #24312F); margin-bottom:6px; }
	.proc-field label .opt{ font-weight:400; color:var(--od-faint, #93A0A0); }
	.proc-field input.form-control, .proc-field textarea.form-control{
		border:1px solid #D3DBDA; border-radius:8px; font-size:13.5px; padding:10px 12px; color:var(--od-ink, #15211F);
	}
	.proc-field input.form-control:focus, .proc-field textarea.form-control:focus{
		border-color:var(--od-accent, #0E7C74); box-shadow:0 0 0 3px rgba(14,124,116,.12); outline:none;
	}
	/* Remarks beside the documents list rather than stacked, so the panel
	   doesn't run to twice the height it needs. */
	.proc-two-col{ display:flex; gap:20px; flex-wrap:wrap; }
	.proc-two-col > *{ flex:1 1 300px; min-width:260px; }
	.proc-two-col .proc-docs{ margin-top:0; }
	.proc-actions{ display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
	.proc-btn-complete{
		background:var(--proc-c, #0E7C74); border-color:var(--proc-c, #0E7C74); color:#fff;
		font-weight:600; padding:10px 18px; border-radius:8px;
	}
	.proc-btn-complete:hover, .proc-btn-complete:focus{
		background:var(--proc-c-dark, #0A5D57); border-color:var(--proc-c-dark, #0A5D57); color:#fff;
	}
	.proc-totals, .proc-invoice-summary{
		display:flex; flex-wrap:wrap; gap:10px 26px;
		padding:14px 16px; background:var(--od-ground, #F7F9F9); border:1px solid var(--od-line, #e3e8e7); border-radius:8px;
	}
	.proc-totals div, .proc-invoice-summary div{ display:flex; flex-direction:column; }
	.proc-totals span, .proc-invoice-summary span{ font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--od-muted-2, #6b7a82); font-weight:600; }
	.proc-totals strong, .proc-invoice-summary strong{ font-size:15px; color:var(--od-ink, #17242b); font-variant-numeric:tabular-nums; font-family:var(--od-mono, monospace); }
	.proc-totals .payable strong, .proc-invoice-summary .payable strong{ color:#6a3fb0; }

	/* ---- Procurement trail: a connected line down the left, with the
	       state - done/current/skipped/pending - carrying the colour, not
	       the phase. Shows every stage, not just what's finished, so the
	       viewer sees at a glance how much of the twelve is left. ---- */
	.proc-trail-head{ display:flex; align-items:baseline; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
	.proc-trail-head h2{ margin:0; font-size:15px; font-weight:600; color:var(--od-ink, #17242b); }
	.proc-trail-progress{ font-size:12px; color:var(--od-muted, #778483); font-family:var(--od-mono, monospace); }
	.proc-timeline{ list-style:none; margin:0; padding:0; }
	.proc-timeline li{ display:flex; gap:14px; }
	.proc-timeline .t-rail{ display:flex; flex-direction:column; align-items:center; width:12px; flex:0 0 12px; }
	.proc-timeline .t-dot{ width:10px; height:10px; border-radius:50%; margin-top:4px; background:#C2CCCB; box-shadow:0 0 0 3px rgba(194,204,203,.28); flex:0 0 10px; }
	.proc-timeline .t-line{ flex:1; width:1px; background:var(--od-line, #E4EAE9); min-height:14px; }
	.proc-timeline li:last-child .t-line{ display:none; }
	.proc-timeline .t-body{ flex:1; min-width:0; padding-bottom:20px; }
	.proc-timeline li:last-child .t-body{ padding-bottom:0; }
	.proc-timeline .t-head{ display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; }
	.proc-timeline .t-name{ font-size:13.5px; font-weight:600; color:var(--od-faint, #778483); }
	.proc-timeline .t-badge{ padding:2px 6px; border-radius:4px; background:var(--od-line-soft, #F1F4F4); color:var(--od-muted, #778483); font-size:10px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; }
	.proc-timeline .t-when{ margin-left:auto; font-size:11px; color:var(--od-faint, #A3AEAD); font-family:var(--od-mono, monospace); }
	.proc-timeline .t-who{ font-size:12px; color:var(--od-muted-2, #778483); margin-top:2px; }
	.proc-timeline .t-remarks{ font-size:13px; color:var(--od-ink-soft, #24312F); margin-top:6px; }
	.proc-timeline .t-meta{ margin-top:8px; display:flex; flex-direction:column; gap:4px; }
	.proc-timeline .t-meta span{ display:flex; gap:6px; font-size:12px; color:var(--od-ink-soft, #24312F); font-family:var(--od-mono, monospace); }
	.proc-timeline .t-meta em{ color:#8B9797; font-style:normal; font-family:var(--od-sans, sans-serif); }
	.proc-timeline .t-docs{ display:flex; flex-wrap:wrap; gap:6px 14px; margin-top:8px; }
	.proc-timeline .t-doc{ font-size:12px; color:var(--od-accent, #0E7C74); font-weight:600; text-decoration:none; }

	/* Done: solid teal. Current: solid amber, highlighted name. Skipped:
	   grey, same ring treatment as done. Pending: hollow - a stage that
	   hasn't happened yet has nothing to show but its place in line. */
	.proc-timeline li.state-done .t-dot{ background:#0E7C74; box-shadow:0 0 0 3px rgba(14,124,116,.14); }
	.proc-timeline li.state-done .t-name{ color:var(--od-ink, #15211F); }
	.proc-timeline li.state-current .t-dot{ background:#C2700C; box-shadow:0 0 0 3px rgba(194,112,12,.16); }
	.proc-timeline li.state-current .t-name{ color:var(--od-ink, #15211F); }
	.proc-timeline li.state-skipped .t-dot{ background:#C2CCCB; box-shadow:0 0 0 3px rgba(194,204,203,.28); }
	.proc-timeline li.state-skipped .t-name{ color:var(--od-muted, #778483); }
	.proc-timeline li.state-pending .t-dot{ background:#FFFFFF; border:1px solid #C2CCCB; box-shadow:0 0 0 3px rgba(194,204,203,.5); }
	.proc-timeline li.state-pending .t-name{ color:var(--od-faint, #A3AEAD); font-weight:500; }
	.proc-timeline .t-doc{ font-size:12.5px; color:#005866; font-weight:600; text-decoration:none; }
	.proc-timeline .t-doc:hover{ text-decoration:underline; }

	.proc-docs{ margin-top:16px; }
	.proc-doc-list{ display:flex; flex-wrap:wrap; gap:8px; }
	.proc-doc-list:empty{ display:none; }
	.proc-doc-chip{
		display:inline-flex; align-items:center; gap:8px; background:#fff; border:1px solid #d6e0e0;
		border-radius:20px; padding:5px 6px 5px 12px; font-size:12.5px; color:#17242b; max-width:100%;
	}
	.proc-doc-chip span{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
	.proc-doc-chip button{
		border:none; background:#eef2f2; color:#6b7a82; border-radius:50%; width:20px; height:20px;
		line-height:1; font-size:13px; cursor:pointer; flex-shrink:0;
	}
	.proc-doc-chip button:hover{ background:#f6d9d3; color:#9e3412; }
	.proc-doc-add{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:10px; }
	.proc-doc-add .form-control{ flex:1 1 200px; min-width:0; }
	.proc-doc-chosen{ font-size:12.5px; color:#6b7a82; }
	.proc-doc-error{ font-size:12.5px; color:#9e3412; margin-top:6px; }
	.proc-doc-error:empty{ display:none; }
	@media print{ .procurement-block{ display:none; } }

	.att-list-row{
		display:flex; align-items:center; gap:12px; border:1px solid #e3e8e7; border-radius:10px;
		padding:10px 12px; background:#fff; text-decoration:none; width:100%; text-align:left;
	}
	.att-list-row:hover{ border-color:#7fb8c2; background:#f4f6f6; text-decoration:none; }
	.att-list-thumb{
		width:38px; height:38px; border-radius:8px; display:flex; align-items:center; justify-content:center;
		color:#fff; font-size:9px; font-weight:800; flex-shrink:0;
	}
	.att-list-row .info{ flex:1; min-width:0; }
	.att-list-row .t{ font-size:13.5px; font-weight:600; color:#17242b; }
	.att-list-row .m{ font-size:11.5px; color:#6b7a82; margin-top:1px; }
	.att-list-row .view{ font-size:12px; font-weight:700; color:#005866; flex-shrink:0; }
</style>
@endsection

@section('home-js')
<script>
$(function () {
	// admin-master.blade.php also does a bare $('#example').DataTable() -
	// runs after this section, but on an already-initialized table that's a
	// no-op, so this is what actually takes effect: a plain item list here
	// doesn't need paging/search/the "Show N entries" picker.
	$('#example').DataTable({
		destroy: true,
		paging: false,
		searching: false,
		info: false,
		// This is a requisition's fixed line items, not a sortable list -
		// and with as many columns as this table has, a sort-icon glyph in
		// every header was the other half of why they wrapped so badly.
		ordering: false,
	});

	function esc(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var thumbColor = { image: '#2f6fed', pdf: '#e5486b', doc: '#5b4fd6' };

	$(document).on('click', '.see-attachments-link', function () {
		$('#view-attachments-item-name').text($(this).data('item-name'));
		var attachments = $(this).data('attachments') || [];
		$('#view-attachments-list').html(attachments.map(function (a) {
			var label = a.kind === 'image' ? 'IMG' : a.kind.toUpperCase();
			return '<a class="att-list-row" href="' + a.view_url + '" target="_blank" rel="noopener">'
				+ '<div class="att-list-thumb" style="background:' + thumbColor[a.kind] + ';">' + label + '</div>'
				+ '<div class="info"><div class="t">' + esc(a.title) + '</div>'
				+ '<div class="m">Attached by ' + esc(a.uploaded_by) + ' &middot; ' + esc(a.uploaded_at) + '</div></div>'
				+ '<span class="view">View</span>'
				+ '</a>';
		}).join(''));
	});

	// --- SSM procurement workflow -------------------------------------
	// Read straight off the meta tag, the same way the requisition wizard
	// does: dataForm.js's CSRF_TOKEN is a var inside its own
	// $(document).ready() closure, so it isn't visible from this script.
	function csrfToken() {
		return $('meta[name="csrf-token"]').attr('content');
	}

	function money(value) {
		return (isFinite(value) ? value : 0).toLocaleString('en-US', {
			minimumFractionDigits: 2, maximumFractionDigits: 2
		});
	}

	// Live line totals and invoice totals as the officer types, so the
	// payable figure they're committing to is visible before they submit
	// rather than only after.
	function recalcInvoice() {
		var subtotal = 0;

		$('input.unit-price').each(function () {
			var id = $(this).data('id');
			var price = parseFloat($(this).val());
			var qty = parseInt($('input.invoice-qty[data-id="' + id + '"]').val(), 10);
			var lineTotal = (isFinite(price) && isFinite(qty)) ? Math.round(price * qty * 100) / 100 : 0;

			subtotal += lineTotal;
			$('td.line_total[data-id="' + id + '"]').text(lineTotal ? money(lineTotal) : '');
		});

		var discount = parseFloat($('#invoice_discount').val());
		discount = isFinite(discount) ? discount : 0;

		$('#inv-subtotal').text(money(subtotal));
		$('#inv-discount').text(money(discount));
		$('#inv-payable').text(money(subtotal - discount));
	}

	$(document).on('input', 'input.unit-price, input.invoice-qty, #invoice_discount', recalcInvoice);
	if ($('#invoice_discount').length) {
		recalcInvoice();
	}

	function submitProcurementStep(orderId, proceed, confirmText) {
		var payload = {
			_token: csrfToken(),
			stage: $('#proc-stage').val(),
			remarks: $('#proc_remarks').val(),
			proceed: proceed ? 1 : 0
		};

		$('.proc-meta').each(function () {
			payload[$(this).data('field')] = $(this).val();
		});

		payload.attachment_ids = window.procurementDocumentIds();

		if (proceed && $('#invoice_discount').length) {
			payload.invoice_no = $('#invoice_no').val();
			payload.invoice_date = $('#invoice_date').val();
			payload.discount = $('#invoice_discount').val();
			payload.unit_price = {};
			payload.invoice_qty = {};
			$('input.unit-price').each(function () {
				payload.unit_price[$(this).data('id')] = $(this).val();
			});
			$('input.invoice-qty').each(function () {
				payload.invoice_qty[$(this).data('id')] = $(this).val();
			});
		}

		swal({
			title: 'Are you sure?',
			text: confirmText,
			type: 'question',
			showCancelButton: true,
			confirmButtonColor: '#005866',
			cancelButtonColor: '#6c757d',
			confirmButtonText: 'Yes, continue',
			showLoaderOnConfirm: true,
			preConfirm: function () {
				return new Promise(function (resolve) {
					$.ajax({
						url: '/procurement/' + orderId + '/complete',
						type: 'post',
						data: payload,
						dataType: 'json'
					})
					.done(function (response) {
						swal('Done!', response[0], 'success').then(function () {
							// Falls back to reloading this same page rather than
							// My Approvals - completing a stage should keep the
							// officer right where they are, ready for the next one.
							window.location.href = response.redirect || window.location.href;
						});
					})
					.fail(function (response) {
						var message = (response.responseJSON && response.responseJSON.message) || 'Something went wrong!';
						swal('Oops...', message, 'error');
					});
				});
			},
			allowOutsideClick: false
		});
	}

	// --- stage documents ----------------------------------------------
	// Files go straight into the uploader's own library; their ids are held
	// in the chip list until the stage completes, which is when the step row
	// they attach to actually comes into existence.
	var procDocs = [];

	function renderProcDocs() {
		$('#proc-doc-list').html(procDocs.map(function (d) {
			return '<span class="proc-doc-chip"><span>' + esc(d.title) + '</span>'
				+ '<button type="button" class="proc-doc-remove" data-id="' + d.id + '" title="Remove">&times;</button></span>';
		}).join(''));
	}

	// jQuery's .trigger('click') runs its own synthetic event first, which
	// doesn't count as a user gesture for opening a file picker - the native
	// method does.
	$(document).on('click', '#proc-doc-browse', function () {
		$('#proc-doc-file')[0].click();
	});

	$(document).on('change', '#proc-doc-file', function () {
		var file = this.files && this.files[0];
		$('#proc-doc-chosen').text(file ? file.name : '');
		$('#proc-doc-upload').prop('disabled', !file);

		// Seed the title from the filename, but never overwrite something the
		// user typed themselves.
		if (file && !$('#proc-doc-title').data('touched')) {
			$('#proc-doc-title').val(file.name.replace(/\.[^.]+$/, ''));
		}
	});

	$(document).on('input', '#proc-doc-title', function () {
		$(this).data('touched', true);
	});

	$(document).on('click', '#proc-doc-upload', function () {
		var file = $('#proc-doc-file')[0].files[0];
		var title = $.trim($('#proc-doc-title').val());

		$('#proc-doc-error').text('');

		if (!file) { return; }
		if (!title) { $('#proc-doc-error').text('Give the document a title.'); return; }

		var data = new FormData();
		data.append('_token', csrfToken());
		data.append('title', title);
		data.append('file', file);

		var $btn = $(this).prop('disabled', true).text('Uploading…');

		$.ajax({
			url: '/attachments/upload',
			type: 'post',
			data: data,
			processData: false,
			contentType: false,
			dataType: 'json'
		})
		.done(function (attachment) {
			procDocs.push({ id: attachment.id, title: attachment.title });
			renderProcDocs();
			$('#proc-doc-title').val('').data('touched', false);
			$('#proc-doc-file').val('');
			$('#proc-doc-chosen').text('');
		})
		.fail(function (response) {
			var errors = response.responseJSON && response.responseJSON.errors;
			var message = errors ? Object.keys(errors).map(function (k) { return errors[k][0]; }).join(' ')
				: ((response.responseJSON && response.responseJSON.message) || 'Upload failed.');
			$('#proc-doc-error').text(message);
		})
		.always(function () {
			$btn.prop('disabled', false).text('Add');
		});
	});

	$(document).on('click', '.proc-doc-remove', function () {
		var id = $(this).data('id');
		procDocs = procDocs.filter(function (d) { return d.id !== id; });
		renderProcDocs();
	});

	// Read by the Approve handler too, for the Master's acknowledgement
	// receipt at the Receipt & Verification stage.
	window.procurementDocumentIds = function () {
		return procDocs.map(function (d) { return d.id; });
	};

	$(document).on('click', '#proc-complete', function () {
		submitProcurementStep($(this).data('id'), true, 'This stage will be recorded and the requisition moves to the next one.');
	});

	$(document).on('click', '#proc-skip', function () {
		submitProcurementStep($(this).data('id'), false, 'This stage will be recorded as not required.');
	});
});
</script>
@endsection

