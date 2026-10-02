{{--
	Right-hand side of the items card header: the invoice currency.

	At Invoice Verification it's a picker (commonly used currencies pinned
	above the full list) that relabels the price columns and totals as it
	changes; once the invoice is in, it's a read-only chip naming the
	currency the prices are in. Nothing at all before pricing exists.

	Expects: $atInvoiceStage, $showPrices, $invoice (OrderInvoice /
	ServiceRequisitionInvoice or null), $invCurrency (code in effect).
--}}
@if($atInvoiceStage)
	@php $currencyOptions = \App\Currency::forPicker(); @endphp
	<div class="inv-currency-picker">
		<label for="invoice_currency">Invoice currency</label>
		<select id="invoice_currency" class="form-control">
			<optgroup label="Common">
				@foreach($currencyOptions['top'] as $currency)
				<option value="{{ $currency->code }}" @selected($currency->code === $invCurrency)>{{ $currency->label() }}</option>
				@endforeach
			</optgroup>
			<optgroup label="All currencies">
				@foreach($currencyOptions['others'] as $currency)
				<option value="{{ $currency->code }}" @selected($currency->code === $invCurrency)>{{ $currency->label() }}</option>
				@endforeach
			</optgroup>
		</select>
	</div>
@elseif($showPrices && $invoice)
	<div class="inv-currency-chip" title="{{ optional($invoice->currency)->name }}">
		Prices in <strong>{{ $invCurrency }}</strong>
	</div>
@endif

@once
<style>
.od-card-head.has-currency{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.inv-currency-picker{ display:flex; align-items:center; gap:10px; }
.inv-currency-picker label{
	margin:0; font-size:11px; letter-spacing:.08em; text-transform:uppercase;
	color:var(--od-muted-2, #778483); font-weight:600; white-space:nowrap;
}
.inv-currency-picker select{ width:auto; min-width:220px; height:36px; font-size:13.5px; }
.inv-currency-chip{
	font-size:12px; color:var(--od-muted, #6B7877); padding:4px 12px; border-radius:999px;
	background:var(--od-accent-tint, #EAF6F4);
}
.inv-currency-chip strong{ color:var(--od-accent-dark, #0A5D57); font-family:var(--od-mono, monospace); }
.proc-totals strong .amt-cur, .proc-invoice-summary strong .amt-cur{ font-size:.72em; font-weight:600; letter-spacing:.04em; opacity:.75; }
.inv-cur{ font-size:.85em; font-weight:500; color:var(--od-muted-2, #778483); }
</style>
@endonce
