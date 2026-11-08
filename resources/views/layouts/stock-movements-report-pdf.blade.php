<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Stock Movements</title>
<style>
	@page { margin: 10mm; }
	*{ box-sizing: border-box; }
	body{ font-family: Arial, Helvetica, sans-serif; color:#000; margin:0; padding:0; font-size:9.5px; }
	h1{ font-size:14px; margin:0 0 2px; }
	.mv-meta{ font-size:10px; color:#333; margin-bottom:8px; }
	table{ width:100%; border-collapse:collapse; }
	th, td{ border:1px solid #777; padding:2px 4px; text-align:left; }
	th{ background:#eee; }
	td.num, th.num{ text-align:right; }
</style>
</head>
<body>
	<h1>Stock Movements &amp; Consumption</h1>
	<div class="mv-meta">
		Vessel: <b>{{ $vessel->name ?? 'Whole fleet' }}</b>
		&nbsp;·&nbsp; {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
		&nbsp;·&nbsp; {{ $rows->count() }} row(s)
		&nbsp;·&nbsp; Printed {{ now()->format('d M Y, h:i A') }}
	</div>
	<table>
		<thead>
			<tr>
				<th>Date</th>
				<th>Vessel</th>
				<th>Item</th>
				<th>Type</th>
				<th class="num">Qty</th>
				<th class="num">Balance After</th>
				<th>Department</th>
				<th>Source</th>
				<th>By</th>
			</tr>
		</thead>
		<tbody>
			@foreach($rows as $row)
			<tr>
				<td>{{ $row['moved_on'] }}</td>
				<td>{{ $row['vessel'] ?? '—' }}</td>
				<td>{{ $row['name'] }}</td>
				<td>{{ $row['type'] }}{{ $row['consumption_type'] ? ' ('.$row['consumption_type'].')' : '' }}</td>
				<td class="num">{{ $row['qty'] > 0 ? '+'.$row['qty'] : $row['qty'] }}</td>
				<td class="num">{{ $row['balance_after'] }}</td>
				<td>{{ $row['department'] ?? '—' }}</td>
				<td>{{ $row['source'] ?? '—' }}</td>
				<td>{{ $row['user'] ?? '—' }}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
