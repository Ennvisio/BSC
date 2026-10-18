<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $requisition->req_no ?: 'Service Requisition' }}</title>
{{-- Standalone page like the item requisition's BSC form (see order-print):
	 nothing from the app's chrome belongs on the paper copy. --}}
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

	.pf-meta{ display: flex; justify-content: space-between; gap: 12px; margin: 14px 2px 8px; font-size: 12.5px; }
	.pf-meta b{ font-weight: 700; }
	.pf-meta .v{ display: inline-block; min-width: 90px; }

	table.pf-items{ width: 100%; border-collapse: collapse; table-layout: fixed; }
	table.pf-items th, table.pf-items td{
		border: 1px solid #000; padding: 3px 4px; font-size: 11px;
		text-align: center; vertical-align: middle; word-wrap: break-word;
	}
	table.pf-items th{ font-weight: 700; line-height: 1.15; }
	table.pf-items td{ height: 22px; }
	table.pf-items td.left{ text-align: left; }

	/* No box: the reason reads as a sentence under its label, not as a form
	   field waiting to be filled - by print time it has already been written. */
	.pf-reason{ margin-top: 14px; font-size: 12px; }
	.pf-reason .lbl{ font-weight: 700; }
	.pf-reason .text{ white-space: pre-line; margin-top: 2px; }

	/* The people who actually signed, not fixed job titles - see
	   ServiceRequisition::signatories(). Laid out four to a row like the paper form's
	   signature strip, wrapping onto another row if more have signed. */
	.pf-signs{
		display: grid; grid-template-columns: repeat(4, 1fr);
		gap: 26px 18px; margin-top: 54px;
	}
	.pf-sign{ text-align: center; font-size: 11px; }
	.pf-sign .img{ height: 46px; display: flex; align-items: flex-end; justify-content: center; overflow: hidden; }
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

<div class="pf-toolbar">
	<button type="button" onclick="window.print()">Print</button>
	<a href="{{ route('service-requisition.show', $requisition->id) }}">Back to requisition</a>
</div>

<div class="pf-head">
	<img class="pf-logo" src="{{ asset('images/logo.png') }}" alt="">
	<div class="pf-org">BANGLADESH SHIPPING CORPORATION</div>
	<div><span class="pf-dept">SHIP REPAIR DEPARTMENT</span></div>
	<div><span class="pf-form">SERVICE REQUISITION FORM &mdash; {{ strtoupper($requisition->typeLabel()) }}</span></div>
</div>

<div class="pf-meta">
	<div><b>Vessel:</b> <span class="v">{{ $requisition->vessel->name ?? '' }}</span></div>
	<div><b>Requisition No.</b> <span class="v">{{ $requisition->req_no }}</span></div>
	<div><b>Date:</b> <span class="v">{{ optional($requisition->req_date)->format('d/m/Y') }}</span></div>
</div>
<div class="pf-meta">
	<div><b>Budget Group:</b> <span class="v">{{ $requisition->budgetGroup->name ?? '—' }}</span></div>
	<div><b>Due Date:</b> <span class="v">{{ $requisition->due_date ? $requisition->due_date->format('d/m/Y') : '—' }}</span></div>
</div>

<table class="pf-items">
	<thead>
		<tr>
			<th style="width:7%;">SL NO</th>
			<th style="width:48%;">{{ $isRenewal ? 'CERTIFICATE TITLE' : 'EQUIPMENT NAME' }}</th>
			<th style="width:30%;">{{ $isRenewal ? 'CATEGORY' : 'MAKER' }}</th>
			<th style="width:15%;">QUANTITY</th>
		</tr>
	</thead>
	<tbody>
		@foreach($requisition->items as $item)
		<tr>
			<td>{{ $loop->iteration }}</td>
			<td class="left">{{ $item->title }}</td>
			<td>{{ $item->subtitle ?: '' }}</td>
			<td>{{ $item->quantity }}</td>
		</tr>
		@endforeach
		@for($i = $requisition->items->count(); $i < $minRows; $i++)
		<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
		@endfor
	</tbody>
</table>

<div class="pf-reason">
	<span class="lbl">Description of Service:</span>
	<div class="text">{{ $requisition->description }}</div>
</div>

@if(count($signatories))
<div class="pf-signs">
	@foreach($signatories as $signatory)
	@php
		// Only draw the image when the file is really on disk - plenty of
		// users have no signature on file, and an unconditional <img> prints
		// a broken-image icon instead of a clean signing line.
		$signPath = $signatory['user']->sign ?? null;
		$hasSign = ! empty($signPath) && file_exists(base_path($signPath));
	@endphp
	<div class="pf-sign">
		<div class="img">@if($hasSign)<img src="{{ asset($signPath) }}" alt="">@endif</div>
		<div class="rule">
			<div class="name">{{ $signatory['user']->name ?? '' }}</div>
			<div class="role">{{ $signatory['role'] }}</div>
		</div>
	</div>
	@endforeach
</div>
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
