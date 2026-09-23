@extends('layouts.admin-master')
@section('main-content')
{{-- Same design language as the item requisition's detail page
	 (view-order-detail.blade.php) - the header/infobar/card/signature system
	 lives there under .order-section and --od-* tokens; reproduced here under
	 the same class and token names so the two pages read as one family, with
	 its own <style> block since each page loads independently. IBM Plex Mono/
	 Sans aren't loaded by admin-master.blade.php (no head-yield to add them
	 to), so they're pulled in here the same way that page does it. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
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
.order-section .card.order-card{ border: none; background: transparent; box-shadow: none; }
.order-section .card-body{ padding: 0; }
.order-section.container{ max-width: 100%; width: 100%; padding-left: 0; padding-right: 0; }
.order-section > .row{ margin-left: 0; margin-right: 0; }
.order-section > .row > [class^="col"]{ padding-left: 0; padding-right: 0; min-width: 0; }

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
.od-actions .btn-secondary{ background: var(--od-surface); border-color: #D3DBDA; color: var(--od-ink-soft); font-weight: 500; }
.od-actions .btn-secondary:hover{ background: var(--od-ground); }
.od-actions .form-control{
	/* Bootstrap's .form-control sets width:100% - inside this flex row that
	   claims the whole line by itself. Give it back an intrinsic width so it
	   sits inline with the buttons instead of pushing them to their own row. */
	width: auto; height: auto; padding: 9px 12px; font-size: 13px; border: 1px solid #D3DBDA;
	border-radius: var(--od-radius-sm); color: var(--od-ink);
}
.od-actions select.form-control{ min-width: 200px; max-width: 260px; }

/* ---- Reject ----
   Literal colours, not var(--od-*): the modal renders outside .order-section
   (down with the other modals), and custom properties only inherit down the
   tree - inside the modal those tokens resolve to nothing. */
.od-btn-reject{ background: #c0392b; border: 1px solid #c0392b; color: #fff; font-weight: 600; }
.od-btn-reject:hover, .od-btn-reject:focus, .od-btn-reject:active{ background: #9c2d21; border-color: #9c2d21; color: #fff; }
.modal-footer .od-btn-reject{ padding: 8px 16px; border-radius: 8px; }
.od-reject-warning{
	font-size: 13px; line-height: 1.55; color: #c0392b;
	background: #fbeae8; border: 1px solid rgba(192,57,43,.22);
	border-radius: 8px; padding: 11px 13px; margin: 0 0 16px;
}
.od-reject-error{ font-size: 12.5px; color: #c0392b; margin-top: 8px; }
.od-reject-error:empty{ display: none; }

.od-rejected{
	background: #fbeae8; border: 1px solid color-mix(in srgb, var(--od-danger) 28%, transparent);
	border-left: 4px solid var(--od-danger); border-radius: var(--od-radius);
	padding: 16px 20px; margin-bottom: 20px;
}
.od-rejected-head{ display: flex; align-items: center; gap: 9px; flex-wrap: wrap; color: var(--od-danger); font-weight: 700; font-size: 15px; }
.od-rejected-reason{ font-size: 14.5px; line-height: 1.6; color: var(--od-ink); margin-top: 10px; max-width: 70ch; white-space: pre-line; }
.od-rejected-by{ font-size: 12.5px; color: var(--od-muted); margin-top: 10px; }
.od-rejected-by .role{ font-family: var(--od-mono); text-transform: uppercase; font-size: 11px; }

/* ---- Info bar ---- */
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
.od-infobar .k{ font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--od-muted-2); font-weight: 600; margin-bottom: 5px; }
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

/* ---- Cards ---- */
.od-card{ background: var(--od-surface); border: 1px solid var(--od-line); border-radius: var(--od-radius); margin-bottom: 20px; overflow: hidden; }
.od-card-head{ padding: 15px 20px; border-bottom: 1px solid var(--od-line-soft); }
.od-card-head h2{ margin: 0; font-size: 15px; font-weight: 600; color: var(--od-ink); }
.od-card-body{ padding: 20px; }
.od-reason-label{
	font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
	color: var(--od-muted-2, #778483); font-weight: 600; margin-bottom: 8px;
}
.od-reason-text{ font-size: 14px; line-height: 1.6; color: var(--od-ink-soft); max-width: 70ch; }

/* ---- Item table ---- */
.od-card .table-responsive{ margin: 0; max-width: 100%; }
.od-card table.sr-item-table{ font-size: 13px; margin: 0; border: none; }
.od-card table.sr-item-table td,
.od-card table.sr-item-table th{ border-left: none !important; border-right: none !important; }
.od-card table.sr-item-table thead th{
	background: var(--od-surface); border-bottom: 1px solid var(--od-line) !important;
	border-top: none !important; font-size: 11.5px; font-weight: 600;
	color: #4C5958; padding: 10px 12px; white-space: nowrap; vertical-align: bottom;
}
.od-card table.sr-item-table tbody td{
	padding: 13px 12px; border-bottom: 1px solid #F0F3F2 !important;
	border-top: none !important; vertical-align: middle; color: var(--od-ink-soft);
	text-align: center;
}
.od-card table.sr-item-table tbody tr:hover{ background: #FAFCFB; }
.od-card table.sr-item-table .serial{ font-family: var(--od-mono); font-weight: 400; font-size: 12.5px; color: var(--od-muted); }
.od-card table.sr-item-table td.num{ font-family: var(--od-mono); font-size: 12.5px; }

/* ---- Authorisation (signatures) ----
   auto-fill + a capped track width, not auto-fit + 1fr: with only 2 or 3
   signatures 1fr stretches each one to fill the whole row, blowing up the
   signing line. auto-fill keeps columns their natural width and leaves the
   rest of the row empty instead. */
.sr-signs{
	display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 220px));
	gap: 28px 32px; margin-top: 10px; justify-content: start;
}
.sr-sign{ display: flex; flex-direction: column; gap: 8px; }
.sr-sign-line{
	height: 52px; border-bottom: 1px solid #C2CCCB;
	display: flex; align-items: flex-end; justify-content: center; overflow: hidden;
}
.sr-sign-line img{ max-height: 50px; max-width: 100%; width: auto; object-fit: contain; display: block; }
.sr-sign-role{ font-family: var(--od-mono); font-size: 11px; color: var(--od-faint); text-transform: uppercase; letter-spacing: .02em; }
.sr-sign-name{ font-size: 13px; font-weight: 600; letter-spacing: .01em; text-transform: uppercase; margin: 0; color: var(--od-ink); }

/* ---- Procurement panel + trail ----
   Same treatment as the item requisition's, minus the Finance hue: a service
   requisition ends when the vessel confirms the work, so it only ever passes
   through Sourcing, Handover and Closed. */
.procurement-block.proc-cat-sourcing{ --proc-c:#0E7C74; --proc-bg:#EAF6F4; --proc-c-dark:#0A5D57; }
.procurement-block.proc-cat-handoff{ --proc-c:#C2700C; --proc-bg:#FFF4E5; --proc-c-dark:#8A4B08; }
.procurement-block.proc-cat-closed{ --proc-c:#0E7C74; --proc-bg:#EAF6F4; --proc-c-dark:#0A5D57; }
.procurement-block{
	border:1px solid var(--od-line, #DFE5E4); border-radius:var(--od-radius, 12px);
	background:var(--od-surface, #fff); padding:20px; margin:0 0 20px;
	display:flex; flex-direction:column; gap:18px;
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
/* Remarks beside the documents list rather than stacked, so the panel doesn't
   run to twice the height it needs. */
.proc-two-col{ display:flex; gap:20px; flex-wrap:wrap; }
.proc-two-col > *{ flex:1 1 300px; min-width:260px; }
.proc-two-col .proc-docs{ margin-top:0; }
.proc-actions{ display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
.proc-actions .btn{ padding:10px 18px; border-radius:8px; font-weight:600; }
.proc-btn-complete{
	background:var(--proc-c, #0E7C74); border-color:var(--proc-c, #0E7C74); color:#fff;
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
/* The priced inputs sit inside the items table, not the panel. */
.od-card table.sr-item-table input.form-control{
	font-family:var(--od-mono); text-align:center; font-size:13px;
	border:1px solid #D3DBDA; border-radius:7px; padding:7px 9px; min-width:90px;
}
.od-card table.sr-item-table input.form-control:focus{
	border-color:var(--od-accent); box-shadow:0 0 0 3px rgba(14,124,116,.12); outline:none;
}

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
.proc-timeline .t-doc{ font-size:12.5px; color:#005866; font-weight:600; text-decoration:none; }
.proc-timeline .t-doc:hover{ text-decoration:underline; }
/* Done: solid teal. Current: solid amber. Skipped: grey. Pending: hollow -
   a stage that hasn't happened yet has nothing to show but its place in line. */
.proc-timeline li.state-done .t-dot{ background:#0E7C74; box-shadow:0 0 0 3px rgba(14,124,116,.14); }
.proc-timeline li.state-done .t-name{ color:var(--od-ink, #15211F); }
.proc-timeline li.state-current .t-dot{ background:#C2700C; box-shadow:0 0 0 3px rgba(194,112,12,.16); }
.proc-timeline li.state-current .t-name{ color:var(--od-ink, #15211F); }
.proc-timeline li.state-skipped .t-dot{ background:#C2CCCB; box-shadow:0 0 0 3px rgba(194,204,203,.28); }
.proc-timeline li.state-skipped .t-name{ color:var(--od-muted, #778483); }
.proc-timeline li.state-pending .t-dot{ background:#FFFFFF; border:1px solid #C2CCCB; box-shadow:0 0 0 3px rgba(194,204,203,.5); }
.proc-timeline li.state-pending .t-name{ color:var(--od-faint, #A3AEAD); font-weight:500; }

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
</style>
@php
	$inProcurement = $requisition->inProcurement();
	$procStage = $requisition->procurement_stage;
	$isDone = $requisition->procurementClosed() || $requisition->currentStageLabel() === 'Approved by GM (SRD)';
	$isRenewal = $requisition->service_type === \App\Http\Controllers\ServiceRequisitionController::TYPE_RENEWAL;

	// Delegating is what hands the requisition into procurement, so it is
	// offered once and only before that happens - afterwards the named officer
	// owns it stage by stage and there is nothing left to delegate.
	$showDelegate = $canAct && $currentRole === 'gm-srd' && ! $inProcurement;
	// Approve likewise: in procurement each stage is completed from its own
	// panel below, not from a single button up here.
	$showApprove = $canAct && ! $inProcurement;
	// Whoever currently holds it can stop it, but only while there is still
	// something to call off - once SRD has completed Delivery the work has
	// been carried out and rejecting it would be ambiguous.
	$showReject = $canAct && ! \App\ServiceProcurementStage::isPostDelivery($procStage);
	$canWorkStage = $canAct && $inProcurement && ! $requisition->procurementClosed();

	// Pricing is shore-side only, the same rule the item requisition uses -
	// gated on user_type rather than by listing the ship roles, so a new ship
	// role can't accidentally inherit price visibility.
	$showPrices = (auth()->user()->role->user_type ?? null) !== 'ship';
	$atInvoiceStage = $canWorkStage && \App\ServiceProcurementStage::capturesInvoice($procStage);
	$hasPricing = $requisition->items->contains(fn ($line) => $line->unit_price !== null);
	$showInvoiceCols = $showPrices && ($atInvoiceStage || $hasPricing);
@endphp
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="od-header">
					<div>
						<div class="od-eyebrow">Service Requisition Details</div>
						<h1 class="od-title">{{ $requisition->vessel->name ?? '' }}</h1>
					</div>
					<div class="od-actions">
						@if($showApprove)
						<button type="button" class="btn btn-primary" id="sr-approve" data-id="{{ $requisition->id }}">
							<i class="fas fa-check-circle"></i> Approve
						</button>
						@endif
						@if($showDelegate)
						{{-- Inline select + button, same pattern as GM (SRD)'s Forward
							 control on the item requisition's detail page - not a modal.
							 Picking someone here starts the procurement workflow. --}}
						<select class="form-control" id="sr-assignee" title="They take the requisition from Administrative Approval through to Delivery.">
							<option value="">Delegate to…</option>
							@foreach($srdOfficers as $roleName => $officers)
							<optgroup label="{{ strtoupper(str_replace('-', ' ', $roleName)) }}">
								@foreach($officers as $officer)
								<option value="{{ $officer->user->id }}">{{ $officer->user->name ?? '' }}</option>
								@endforeach
							</optgroup>
							@endforeach
						</select>
						<button type="button" class="btn btn-info" id="sr-delegate" data-id="{{ $requisition->id }}"><i class="fas fa-angle-double-right"></i> Delegate</button>
						@endif
						@if($showReject)
						<button type="button" class="btn od-btn-reject" id="sr-reject-open" data-toggle="modal" data-target="#sr-reject-modal">
							<i class="fas fa-times-circle"></i> Reject
						</button>
						@endif
					</div>
				</div>
				<div class="card-body">
					@if($requisition->isRejected())
					<div class="od-rejected">
						<div class="od-rejected-head"><i class="fas fa-times-circle"></i><span>Rejected</span></div>
						<div class="od-rejected-reason">{{ $requisition->rejection_reason }}</div>
						<div class="od-rejected-by">
							{{ $requisition->rejectedBy->name ?? 'Unknown user' }}
							@if($requisition->rejected_by_role)
							<span class="role">{{ str_replace('-', ' ', $requisition->rejected_by_role) }}</span>
							@endif
							@if($requisition->rejected_at)
							&middot; {{ $requisition->rejected_at->format('d M Y, h:i A') }}
							@endif
						</div>
					</div>
					@endif

					<div class="od-infobar">
						<div><div class="k">Req. No.</div><div class="v mono">{{ $requisition->req_no ?: '—' }}</div></div>
						<div><div class="k">Type</div><div class="v">{{ $requisition->typeLabel() }}</div></div>
						<div><div class="k">Budget Group</div><div class="v">{{ $requisition->budgetGroup->name ?? '—' }}</div></div>
						<div><div class="k">Raised By</div><div class="v">{{ $requisition->creator->name ?? '' }}</div></div>
						<div><div class="k">Date</div><div class="v mono">{{ optional($requisition->req_date)->format('d M Y') }}</div></div>
						<div><div class="k">Due Date</div><div class="v mono">{{ $requisition->due_date ? $requisition->due_date->format('d M Y') : '—' }}</div></div>
						<div>
							<div class="k">Stage</div>
							<div class="od-stage-pill {{ $isDone ? 'is-done' : '' }}"><span class="dot"></span>{{ $requisition->currentStageLabel() }}</div>
						</div>
					</div>

					<div class="od-card">
						<div class="od-card-head"><h2>{{ $isRenewal ? 'Certificates for Renewal' : 'Equipment for Repair' }}</h2></div>
						<div class="table-responsive">
							<table class="table table-bordered sr-item-table">
								<thead>
									<tr>
										<th>No.</th>
										<th>{{ $isRenewal ? 'Certificate Title' : 'Equipment Name' }}</th>
										<th>{{ $isRenewal ? 'Category' : 'Maker' }}</th>
										<th class="num">Quantity</th>
										{{-- Invoice columns appear once there is something to
											 enter, or something already entered to show. --}}
										@if($showInvoiceCols)
										<th class="num">Invoice Qty</th>
										<th class="num">Unit Price</th>
										<th class="num">Line Total</th>
										@endif
									</tr>
								</thead>
								<tbody>
									@foreach($requisition->items as $item)
									<tr>
										<td><b class="serial">{{ $loop->iteration }}</b></td>
										<td>{{ $item->title }}</td>
										<td>{{ $item->subtitle ?: '—' }}</td>
										<td class="num">{{ $item->quantity }}</td>
										@if($showInvoiceCols)
										<td class="num">
											@if($atInvoiceStage)
											{{-- Defaults to what was ordered, so billing for
												 more than that is a visible edit rather than
												 the path of least resistance. --}}
											<input type="number" min="0" data-id="{{ $item->id }}" class="form-control invoice-qty"
												value="{{ $item->invoice_qty ?? $item->quantity }}">
											@else
											{{ $item->invoice_qty }}
											@endif
										</td>
										<td class="num">
											@if($atInvoiceStage)
											<input type="number" step="0.01" min="0" data-id="{{ $item->id }}" class="form-control unit-price"
												value="{{ $item->unit_price }}">
											@else
											{{ $item->unit_price !== null ? number_format($item->unit_price, 2) : '' }}
											@endif
										</td>
										<td class="num line_total" data-id="{{ $item->id }}">
											{{ $item->line_total !== null ? number_format($item->line_total, 2) : '' }}
										</td>
										@endif
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>

					<div class="od-card">
						<div class="od-card-body">
							<div class="od-reason-label">Description</div>
							<div class="od-reason-text">{{ $requisition->description }}</div>
						</div>
					</div>

					{{-- The procurement workflow GM's delegation hands this into.
						 The panel is the action for whichever stage is current; the
						 trail below it is everything already done. --}}
					@if($inProcurement)
					@php
						$procCategory = \App\ServiceProcurementStage::category(
							$requisition->procurementClosed() ? \App\ServiceProcurementStage::CLOSED : $procStage
						);

						// The trail shows the WHOLE sequence, not just what has
						// happened - done stages, the current one (highlighted) and
						// what is still ahead (hollow), so the viewer can see how
						// much is left without knowing the list by heart. Completed
						// rows carry their real recorded data; current/pending rows
						// are synthesized here since they have no step row yet.
						$completedBySlug = $requisition->procurementSteps->keyBy('step');
						$trail = [];
						foreach (\App\ServiceProcurementStage::SEQUENCE as $slug) {
							if ($slug === \App\ServiceProcurementStage::CLOSED) {
								continue;
							}
							if ($step = $completedBySlug->get($slug)) {
								$trail[] = ['step' => $step, 'slug' => $slug, 'state' => $step->wasSkipped() ? 'skipped' : 'done'];
							} elseif ($slug === $procStage && ! $requisition->procurementClosed()) {
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
									<span class="proc-phase-tag">{{ \App\ServiceProcurementStage::CATEGORY_LABELS[$procCategory] }}</span>
								</div>
								<div class="proc-stage-name">
									{{ $requisition->procurementClosed() ? 'Closed' : \App\ServiceProcurementStage::label($procStage) }}
								</div>
							</div>
						</div>

						@if($canWorkStage)
						<form id="service-proc-form" class="proc-form">
							<input type="hidden" id="proc-stage" value="{{ $procStage }}">

							@if($procStage === \App\ServiceProcurementStage::RECEIPT_VERIFICATION)
							<p class="proc-hint">
								Confirm the work was carried out on board as ordered. Attach the signed
								acknowledgement here first &mdash; you can add more than one. It then
								goes back to SRD for invoicing and payment.
							</p>
							@endif

							@if($atInvoiceStage)
							<p class="proc-hint">
								Enter what the yard actually billed for each line in the table above,
								then the invoice details here.
							</p>
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

							@if(\App\ServiceProcurementStage::metaFields($procStage))
							<div class="proc-fields">
								@foreach(\App\ServiceProcurementStage::metaFields($procStage) as $field)
								@php
									$metaType = str_contains($field, '_date') || $field === 'completed_on' ? 'date'
										: (str_contains($field, 'amount') || in_array($field, ['vendors_invited', 'bids_received']) ? 'number' : 'text');
								@endphp
								<div class="proc-field">
									<label for="meta_{{ $field }}">{{ \App\ServiceProcurementStage::metaLabel($field) }}</label>
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
								@include('partials.procurement-attachments', ['stageLabel' => \App\ServiceProcurementStage::label($procStage)])
							</div>

							<div class="proc-actions">
								@if(\App\ServiceProcurementStage::isSkippable($procStage))
								<button type="button" class="btn btn-secondary" id="proc-skip" data-id="{{ $requisition->id }}">
									Not required
								</button>
								@endif
								<button type="button" class="btn proc-btn-complete" id="proc-complete" data-id="{{ $requisition->id }}">
									<i class="fas fa-check-circle"></i>
									Complete {{ \App\ServiceProcurementStage::label($procStage) }}
								</button>
							</div>
						</form>
						@elseif(! $requisition->procurementClosed())
						<p class="proc-waiting">
							@if(\App\ServiceProcurementStage::owner($procStage) === \App\ServiceProcurementStage::OWNER_SHIP)
							Waiting on the vessel to confirm the work was carried out.
							@else
							Waiting on {{ optional(\App\User::find($requisition->approval->assigned_to_srd))->name ?? 'the assigned SRD officer' }}.
							@endif
						</p>
						@endif

						@if($showPrices && $requisition->invoice)
						<div class="proc-invoice-summary">
							<div><span>Invoice</span><strong>{{ $requisition->invoice->invoice_no ?: '—' }}</strong></div>
							<div><span>Subtotal</span><strong>{{ number_format($requisition->items->sum('line_total'), 2) }}</strong></div>
							<div><span>Discount</span><strong>{{ number_format($requisition->invoice->discount, 2) }}</strong></div>
							<div class="payable"><span>Payable</span><strong>{{ number_format($requisition->invoice->payable, 2) }}</strong></div>
						</div>
						@endif
					</div>

					<div class="od-card">
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
											<span class="t-name">{{ \App\ServiceProcurementStage::label($row['slug']) }}</span>
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
											{{-- Some entries are lists rather than single values - the
												 certificates a Renewal rolled forward, for one. --}}
											@foreach((array) $value as $line)
											<span><em>{{ \App\ServiceProcurementStage::metaLabel($key) }}:</em> {{ $line }}</span>
											@endforeach
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

					{{-- Authorisation - same card/label treatment as the item
						 requisition's (.od-card > .od-reason-label "Authorisation"),
						 with its own signature grid (.sr-signs) that caps each
						 column's width instead of the item page's auto-fit/1fr,
						 which stretches a 2-3 signature row across the whole card. --}}
					<div class="od-card">
						<div class="od-card-body">
							<div class="od-reason-label">Authorisation</div>
							@if(count($signatories))
							<div class="sr-signs">
								@foreach($signatories as $signatory)
								@php
									$signPath = $signatory['user']->sign ?? null;
									$hasSign = ! empty($signPath) && file_exists(base_path($signPath));
								@endphp
								<div class="sr-sign">
									<div class="sr-sign-line">
										@if($hasSign)
										<img src="{{ url('/'.$signPath) }}" alt="Signature of {{ $signatory['user']->name }}">
										@endif
									</div>
									<div class="sr-sign-role">{{ $signatory['role'] }}</div>
									<div class="sr-sign-name">{{ $signatory['user']->name ?? '—' }}</div>
								</div>
								@endforeach
							</div>
							@else
							<p class="text-muted mb-0">Nobody has signed this requisition yet.</p>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Reject modal - same design as the item requisition's (reject-order-modal
	 in view-order-detail.blade.php): a Bootstrap modal rather than a
	 SweetAlert input, so the warning has room to be read and the reason
	 field behaves like every other textarea on the page. --}}
@if($showReject)
<div class="modal fade" id="sr-reject-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document" style="max-width:520px;">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<div style="font-size:11.5px;color:#6b7a82;font-weight:600;margin-bottom:2px;">
						{{ $requisition->req_no ?: 'This requisition' }}
					</div>
					<h5 class="modal-title">Reject requisition</h5>
				</div>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="od-reject-warning">
					Rejecting is final. The requisition stops here and leaves every queue &mdash;
					it cannot be reopened, and a new one has to be raised in its place.
				</p>
				<label class="od-reason-label" for="sr-reason">Reason for rejection</label>
				<textarea class="form-control" id="sr-reason" rows="4" placeholder="Why is this requisition being rejected?"></textarea>
				<div class="od-reject-error" id="sr-reject-error"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn od-btn-reject" id="sr-reject" data-id="{{ $requisition->id }}">
					<i class="fas fa-times-circle"></i> Reject requisition
				</button>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@section('home-js')
<script>
$(function () {
	var token = '{{ csrf_token() }}';

	function post(url, data, onDone) {
		$.post(url, $.extend({ _token: token }, data))
			.done(function (res) {
				swal('Done', res.message, 'success').then(function () {
					window.location.href = res.redirect || '{{ route("service-requisition.index") }}';
				});
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.';
				if (onDone) { onDone(msg); } else { swal('Not done', msg, 'error'); }
			});
	}

	$('#sr-approve').on('click', function () {
		var id = $(this).data('id');
		swal({
			title: 'Approve this requisition?', type: 'question',
			showCancelButton: true, confirmButtonText: 'Yes, approve',
		}).then(function (r) {
			if (r === true || (r && r.value)) { post('{{ route("service-requisition.approve") }}', { id: id }); }
		});
	});

	$('#sr-delegate').on('click', function () {
		var assignee = $('#sr-assignee').val();
		if (!assignee) { swal('Choose a reviewer', 'Pick who should review this first.', 'warning'); return; }
		post('{{ route("service-requisition.delegate") }}', { id: $(this).data('id'), assigned_to: assignee });
	});

	$('#sr-reject').on('click', function () {
		var reason = $.trim($('#sr-reason').val());
		$('#sr-reject-error').text('');
		if (!reason) { $('#sr-reject-error').text('Please give a reason.'); return; }
		post('{{ route("service-requisition.reject") }}', { id: $(this).data('id'), reason: reason }, function (msg) {
			$('#sr-reject-error').text(msg);
		});
	});

	// --- procurement stages -------------------------------------------
	var procDocs = [];

	function esc(text) { return $('<div>').text(text == null ? '' : text).html(); }

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

	$(document).on('input', '#proc-doc-title', function () { $(this).data('touched', true); });

	$(document).on('click', '#proc-doc-upload', function () {
		var file = $('#proc-doc-file')[0].files[0];
		var title = $.trim($('#proc-doc-title').val());

		$('#proc-doc-error').text('');
		if (!file) { return; }
		if (!title) { $('#proc-doc-error').text('Give the document a title.'); return; }

		var data = new FormData();
		data.append('_token', token);
		data.append('title', title);
		data.append('file', file);

		var $btn = $(this).prop('disabled', true).text('Uploading…');

		$.ajax({ url: '/attachments/upload', type: 'post', data: data, processData: false, contentType: false, dataType: 'json' })
			.done(function (attachment) {
				procDocs.push({ id: attachment.id, title: attachment.title });
				renderProcDocs();
				$('#proc-doc-title').val('').data('touched', false);
				$('#proc-doc-file').val('');
				$('#proc-doc-chosen').text('');
			})
			.fail(function (xhr) {
				var errors = xhr.responseJSON && xhr.responseJSON.errors;
				$('#proc-doc-error').text(errors
					? Object.keys(errors).map(function (k) { return errors[k][0]; }).join(' ')
					: ((xhr.responseJSON && xhr.responseJSON.message) || 'Upload failed.'));
			})
			.always(function () { $btn.prop('disabled', false).text('Add'); });
	});

	$(document).on('click', '.proc-doc-remove', function () {
		var id = $(this).data('id');
		procDocs = procDocs.filter(function (d) { return d.id !== id; });
		renderProcDocs();
	});

	// --- invoice totals, recomputed as the SRD officer types ----------
	function money(n) {
		return (isFinite(n) ? n : 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

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
	if ($('#invoice_discount').length) { recalcInvoice(); }

	function submitStage(id, proceed, confirmText) {
		var payload = {
			_token: token,
			stage: $('#proc-stage').val(),
			remarks: $('#proc_remarks').val(),
			proceed: proceed ? 1 : 0,
			attachment_ids: procDocs.map(function (d) { return d.id; })
		};

		$('.proc-meta').each(function () { payload[$(this).data('field')] = $(this).val(); });

		// Invoice Verification carries the priced lines along with the stage -
		// the figures and the stage advancing are one event server-side.
		if (proceed && $('#invoice_discount').length) {
			payload.invoice_no = $('#invoice_no').val();
			payload.invoice_date = $('#invoice_date').val();
			payload.discount = $('#invoice_discount').val();
			payload.unit_price = {};
			payload.invoice_qty = {};
			$('input.unit-price').each(function () { payload.unit_price[$(this).data('id')] = $(this).val(); });
			$('input.invoice-qty').each(function () { payload.invoice_qty[$(this).data('id')] = $(this).val(); });
		}

		swal({
			title: 'Are you sure?', text: confirmText, type: 'question',
			showCancelButton: true, confirmButtonText: 'Yes, continue',
		}).then(function (r) {
			if (!(r === true || (r && r.value))) { return; }

			$.post('/service-procurement/' + id + '/complete', payload)
				.done(function (res) {
					swal('Done!', res.message, 'success').then(function () {
						window.location.href = res.redirect || window.location.href;
					});
				})
				.fail(function (xhr) {
					swal('Oops...', (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.', 'error');
				});
		});
	}

	$(document).on('click', '#proc-complete', function () {
		submitStage($(this).data('id'), true, 'This stage will be recorded and the requisition moves to the next one.');
	});

	$(document).on('click', '#proc-skip', function () {
		submitStage($(this).data('id'), false, 'This stage will be recorded as not required and skipped.');
	});
});
</script>
@endsection
