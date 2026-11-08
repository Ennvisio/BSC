<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Current Stock{{ $vessel ? ' - '.$vessel->name : '' }}</title>
{{-- A standalone page, not the admin layout - same reasoning as
	 order-print.blade.php: nothing from the app's chrome belongs on a
	 printed stock sheet. --}}
<style>
	@page { size: A4 landscape; margin: 10mm; }
	*{ box-sizing: border-box; }
	body{ font-family: Arial, Helvetica, sans-serif; color:#000; margin:0; padding:14px 18px; font-size:11px; }
	h1{ font-size:16px; margin:0 0 2px; }
	.cs-meta{ font-size:11.5px; color:#333; margin-bottom:12px; }
	table{ width:100%; border-collapse:collapse; }
	th, td{ border:1px solid #777; padding:3px 5px; text-align:left; }
	th{ background:#eee; }
	td.num, th.num{ text-align:right; }
	.low{ font-weight:700; }
	@media print {
		.cs-print-btn{ display:none; }
	}
	.cs-print-btn{ margin-bottom:12px; }
</style>
</head>
<body>
	<button type="button" class="cs-print-btn" onclick="window.print()">Print</button>
	<h1>Current Stock</h1>
	<div class="cs-meta">
		Vessel: <b>{{ $vessel->name ?? '—' }}</b>
		&nbsp;·&nbsp; Category: <b>{{ $category->name ?? '—' }}</b>
		&nbsp;·&nbsp; {{ $rows->count() }} item(s)
		&nbsp;·&nbsp; Printed {{ now()->format('d M Y, h:i A') }}
	</div>
	<table>
		<thead>
			<tr>
				<th>Item Name</th>
				<th>Article Number</th>
				<th>IMPA Code</th>
				<th>Unit</th>
				<th class="num">Opening</th>
				<th class="num">Received</th>
				<th class="num">Consumed</th>
				<th class="num">Net Adj.</th>
				<th class="num">Current Stock</th>
				<th class="num">Min Qty</th>
				<th class="num">Last Supply Qty</th>
				<th>Last Supply Date</th>
				<th>Last Consumed</th>
			</tr>
		</thead>
		<tbody>
			@foreach($rows as $row)
			<tr>
				<td>{{ $row['name'] }}</td>
				<td>{{ $row['article_number'] ?: '—' }}</td>
				<td>{{ $row['impa_code'] ?: '—' }}</td>
				<td>{{ $row['unit'] }}</td>
				<td class="num">{{ $row['opening_stock'] ?? '—' }}</td>
				<td class="num">{{ $row['total_received'] }}</td>
				<td class="num">{{ $row['total_consumed'] }}</td>
				<td class="num">{{ $row['net_adjustments'] }}</td>
				<td class="num {{ $row['low'] ? 'low' : '' }}">{{ $row['stock_qty'] }}{{ $row['low'] ? ' (low)' : '' }}</td>
				<td class="num">{{ $row['min_qty'] ?? '—' }}</td>
				<td class="num">{{ $row['last_supply_qty'] ?? '—' }}</td>
				<td>{{ $row['last_supply_date'] ?? '—' }}</td>
				<td>{{ $row['last_consumed_date'] ?? '—' }}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
