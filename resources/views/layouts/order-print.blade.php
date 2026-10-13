<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $order->req_no ?: 'Requisition' }}</title>
{{-- A standalone page, not the admin layout: this is the BSC paper form, and
	 nothing from the app's chrome belongs on it. Replaces the old approach of
	 scraping elements out of the detail page into a popup with
	 print-pdf-custom.js, which could only ever print one shape of table -
	 these forms differ per category (see App\RequisitionPrintForm). --}}
<style>
	@page { size: A4 portrait; margin: 12mm 10mm; }
	*{ box-sizing: border-box; }
	body{
		font-family: 'Times New Roman', Times, serif; color: #000;
		margin: 0; padding: 14px 18px; background: #fff; font-size: 13px;
	}
	.pf-head{ position: relative; text-align: center; margin-bottom: 14px; }
	.pf-logo{ position: absolute; left: 0; top: 0; width: 62px; }
	.pf-org{ font-size: 17px; font-weight: 700; letter-spacing: .3px; }
	.pf-dept, .pf-form{
		font-weight: 700; text-decoration: underline; letter-spacing: 1.5px;
		display: inline-block; margin-top: 2px;
	}
	.pf-dept{ font-size: 13.5px; }
	.pf-form{ font-size: 13.5px; }

	{{-- display:table/table-cell rather than flex: this view is also rendered
		 server-side to PDF (OrderPrintController@pdf, via dompdf), which has
		 no flexbox support - table layout is the one thing both a real
		 browser and dompdf render identically. --}}
	.pf-meta{ display: table; width: 100%; table-layout: fixed; margin: 14px 2px 8px; font-size: 12.5px; }
	.pf-meta > div{ display: table-cell; }
	.pf-meta b{ font-weight: 700; }
	.pf-meta .v{ display: inline-block; min-width: 90px; }

	table.pf-machinery{ width: 100%; border-collapse: collapse; margin-bottom: 14px; }
	table.pf-machinery th, table.pf-machinery td{ border: 1px solid #000; padding: 4px 6px; font-size: 12px; text-align: left; height: 24px; }
	table.pf-machinery th{ width: 24%; font-weight: 700; }
	table.pf-items{ width: 100%; border-collapse: collapse; table-layout: fixed; }
	table.pf-items th, table.pf-items td{
		border: 1px solid #000; padding: 3px 4px; font-size: 11px;
		text-align: center; vertical-align: middle; word-wrap: break-word;
	}
	table.pf-items th{ font-weight: 700; line-height: 1.15; }
	table.pf-items td{ height: 22px; }
	table.pf-items td.left{ text-align: left; }
	/* name_with_spec puts the specification on its own line under the name. */
	table.pf-items td.left{ white-space: pre-line; }

	/* No box: the reason reads as a sentence under its label, not as a form
	   field waiting to be filled - by print time it has already been written. */
	.pf-reason{ margin-top: 14px; font-size: 12px; }
	.pf-reason .lbl{ font-weight: 700; }
	.pf-reason .text{ white-space: pre-line; margin-top: 2px; }

	/* The people who actually signed, not fixed job titles - see
	   Order::signatories(). Laid out four to a row like the paper form's
	   signature strip, wrapping onto another row if more have signed. */
	{{-- A real <table>, chunked 4-per-row in PHP below, rather than CSS grid
		 or floats: dompdf doesn't support grid at all, and its float
		 wrapping turned out not to reliably start a new row either (a 5th/
		 6th signatory overlapped the 4th instead of wrapping) - table rows
		 are the one layout dompdf gets right every time. --}}
	table.pf-signs{ width: 100%; border-collapse: collapse; margin-top: 54px; table-layout: fixed; }
	.pf-sign{ width: 25%; padding: 0 9px 26px; box-sizing: border-box; text-align: center; font-size: 11px; vertical-align: top; }
	/* Likewise a real <table> rather than display:table-cell on a bare div:
	   dompdf honours vertical-align on an actual table cell, which is what
	   bottom-aligns a short signature inside the fixed-height box without
	   overflowing it. */
	.pf-sign .img{ height: 46px; }
	.pf-sign .img table{ width: 100%; height: 46px; border-collapse: collapse; }
	.pf-sign .img td{ vertical-align: bottom; text-align: center; padding: 0; }
	.pf-sign .img img{ max-height: 46px; max-width: 100%; }
	.pf-sign .rule{ border-top: 1px solid #000; margin-top: 2px; padding-top: 3px; }
	.pf-sign .name{ font-weight: 700; text-transform: uppercase; }
	.pf-sign .role{ font-size: 10px; }
	.pf-signs-empty{ margin-top: 54px; font-size: 11.5px; font-style: italic; }

	.pf-toolbar{ text-align: center; margin-bottom: 14px; }
	.pf-toolbar button{
		font-family: system-ui, sans-serif; font-size: 13px; padding: 8px 18px; margin: 0 4px;
		border: 1px solid #0E7C74; background: #0E7C74; color: #fff; border-radius: 6px; cursor: pointer;
	}
	.pf-toolbar a{
		font-family: system-ui, sans-serif; font-size: 13px; padding: 8px 18px;
		border: 1px solid #D3DBDA; border-radius: 6px; color: #24312F; text-decoration: none;
	}
	@media print{ .pf-toolbar{ display: none; } body{ padding: 0; } }
</style>
</head>
<body>

{{-- Meaningless in a downloaded PDF - there's no page to print again or
	 navigate back from - so it's left out of that render entirely rather
	 than just hidden with @media print, which dompdf doesn't apply here. --}}
@unless($forPdf ?? false)
<div class="pf-toolbar">
	<button type="button" onclick="window.print()">Print</button>
	<a href="{{ route('view.order.detail', $order->id) }}">Back to requisition</a>
</div>
@endunless

<div class="pf-head">
	<img class="pf-logo" src="{{ $forPdf ?? false ? public_path('images/logo.png') : asset('images/logo.png') }}" alt="">
	<div class="pf-org">BANGLADESH SHIPPING CORPORATION</div>
	<div><span class="pf-dept">SHIP REPAIR DEPARTMENT</span></div>
	<div><span class="pf-form">REQUISITION FORM FOR {{ $formTitle }}</span></div>
</div>

<div class="pf-meta">
	<div><b>Vessel:</b> <span class="v">{{ $order->vessel->name ?? '' }}</span></div>
	<div><b>Requisition No.</b> <span class="v">{{ $order->req_no }}</span></div>
	<div><b>Date:</b> <span class="v">{{ $order->req_date ? \Carbon\Carbon::parse($order->req_date)->format('d/m/Y') : '' }}</span></div>
	<div><b>PLACE:</b> <span class="v">{{ $order->port_name }}</span></div>
</div>

{{-- Spares only: which machinery the spares are for, as typed by the officer
	 who raised it. Five fixed rows like the paper form - a blank one stays
	 blank, so it can still be written in by hand. --}}
@if(\App\RequisitionPrintForm::hasMachineryBlock($order->category->symbol ?? null))
<table class="pf-machinery">
	@foreach(\App\RequisitionPrintForm::MACHINERY_FIELDS as $column => $label)
	<tr><th>{{ $label }}</th><td>{{ $order->{$column} }}</td></tr>
	@endforeach
</table>
@endif

<table class="pf-items">
	<thead>
		<tr>
			<th style="width:5%;">SL NO</th>
			@foreach($columns as $column)
			<th style="width:{{ $column['width'] }};">{{ $column['label'] }}</th>
			@endforeach
		</tr>
	</thead>
	<tbody>
		@foreach($order->orderItems as $line)
		<tr>
			<td>{{ $loop->iteration }}</td>
			@foreach($columns as $column)
			<td class="{{ ($column['align'] ?? '') === 'left' ? 'left' : '' }}">{{ \App\RequisitionPrintForm::valueFor($column['get'], $line, $liveStock) }}</td>
			@endforeach
		</tr>
		@endforeach
		{{-- Padded out to the paper form's own row count, so a short
			 requisition still prints as a form rather than a stub. --}}
		@for($i = $order->orderItems->count(); $i < $minRows; $i++)
		<tr>
			<td>&nbsp;</td>
			@foreach($columns as $column)<td>&nbsp;</td>@endforeach
		</tr>
		@endfor
	</tbody>
</table>

<div class="pf-reason">
	<span class="lbl">Reason of Requisition (filled by Master/Chief Engineer):</span>
	<div class="text">{{ $order->reason }}</div>
</div>

@if(count($signatories))
<table class="pf-signs">
	@foreach(array_chunk($signatories, 4) as $row)
	<tr>
		@foreach($row as $signatory)
		@php
			// Only draw the image when the file is really on disk - plenty of
			// users have no signature on file, and an unconditional <img> prints
			// a broken-image icon instead of a clean signing line.
			$signPath = $signatory['user']->sign ?? null;
			$hasSign = ! empty($signPath) && file_exists(base_path($signPath));
		@endphp
		<td class="pf-sign">
			<div class="img"><table><tr><td>@if($hasSign)<img src="{{ ($forPdf ?? false) ? base_path($signPath) : asset($signPath) }}" alt="">@endif</td></tr></table></div>
			<div class="rule">
				<div class="name">{{ $signatory['user']->name ?? '' }}</div>
				<div class="role">{{ $signatory['role'] }}</div>
			</div>
		</td>
		@endforeach
		{{-- A short last row still needs its empty cells, or table-layout:fixed
			 would stretch the few that did sign to fill the whole row width. --}}
		@for($i = count($row); $i < 4; $i++)<td class="pf-sign"></td>@endfor
	</tr>
	@endforeach
</table>
@else
<div class="pf-signs-empty">Nobody has signed this requisition yet.</div>
@endif

<script>
	// Opened straight from the Print button, so go to the dialog on its own -
	// the toolbar above is for reprinting without a round trip.
	if (new URLSearchParams(window.location.search).get('auto') === '1') {
		window.addEventListener('load', function () { window.print(); });
	}
</script>
</body>
</html>
