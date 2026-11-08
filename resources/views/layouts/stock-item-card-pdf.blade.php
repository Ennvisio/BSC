<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Stock Card - {{ $item->name }}</title>
<style>
	@page { margin: 12mm 10mm; }
	*{ box-sizing: border-box; }
	body{ font-family: Arial, Helvetica, sans-serif; color:#000; margin:0; padding:0; font-size:10.5px; }
	h1{ font-size:15px; margin:0 0 2px; }
	.sc-meta{ font-size:10.5px; color:#333; margin-bottom:4px; }
	.sc-figures{ width:100%; border-collapse:collapse; margin:10px 0; }
	.sc-figures td{ border:1px solid #777; padding:4px 6px; text-align:center; }
	.sc-figures td b{ display:block; font-size:13px; }
	.sc-figures td span{ font-size:9.5px; color:#444; }
	table.sc-rows{ width:100%; border-collapse:collapse; margin-top:8px; }
	table.sc-rows th, table.sc-rows td{ border:1px solid #777; padding:3px 5px; text-align:left; }
	table.sc-rows th{ background:#eee; }
	td.num, th.num{ text-align:right; }
</style>
</head>
<body>
	<h1>Item Stock Card — {{ $item->name }}</h1>
	<div class="sc-meta">
		Vessel: <b>{{ $vessel->name ?? '—' }}</b>
		&nbsp;·&nbsp; Article #: <b>{{ $item->article_number ?: '—' }}</b>
		&nbsp;·&nbsp; IMPA: <b>{{ $item->impa_code ?: '—' }}</b>
		&nbsp;·&nbsp; Unit: <b>{{ $item->unit }}</b>
		&nbsp;·&nbsp; {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
		&nbsp;·&nbsp; Printed {{ now()->format('d M Y, h:i A') }}
	</div>

	<table class="sc-figures">
		<tr>
			<td><b>{{ $vesselItem->opening_stock ?? '—' }}</b><span>Opening</span></td>
			<td><b>{{ $vesselItem->stock_qty ?? 0 }}</b><span>Current Stock</span></td>
			<td><b>{{ $vesselItem->min_qty ?? '—' }}</b><span>Min Qty</span></td>
			<td><b>{{ $vesselItem->last_supply_qty ?? '—' }}</b><span>Last Supply Qty</span></td>
			<td><b>{{ $vesselItem->last_supply_date ? $vesselItem->last_supply_date->format('d M Y') : '—' }}</b><span>Last Supply Date</span></td>
		</tr>
	</table>

	<table class="sc-rows">
		<thead>
			<tr>
				<th>Date</th>
				<th>Type</th>
				<th class="num">Qty</th>
				<th class="num">Balance After</th>
				<th>Source</th>
				<th>Remarks</th>
				<th>By</th>
			</tr>
		</thead>
		<tbody>
			@forelse($movements as $m)
			<tr>
				<td>{{ $m->moved_on->format('d M Y') }}</td>
				<td>{{ $m->typeLabel() }}</td>
				<td class="num">{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</td>
				<td class="num">{{ $m->balance_after }}</td>
				<td>
					@if($m->order)
						Requisition {{ $m->order->req_no ?: '#'.$m->order_id }}
					@elseif($m->stockConsumption)
						{{ $m->stockConsumption->typeLabel() }}: {{ \Illuminate\Support\Str::limit($m->stockConsumption->purpose, 50) }}
					@elseif($m->stockImport)
						Upload: {{ $m->stockImport->filename }}
					@else
						—
					@endif
				</td>
				<td>{{ \Illuminate\Support\Str::limit($m->remarks, 60) }}</td>
				<td>{{ $m->user->name ?? '—' }}</td>
			</tr>
			@empty
			<tr><td colspan="7" style="text-align:center;">No movements in this date range.</td></tr>
			@endforelse
		</tbody>
	</table>
</body>
</html>
