<!doctype html>
<html><head><meta charset="utf-8"><title>Vaccine Stock Report</title><style>
body{font-family:DejaVu Sans,Arial,sans-serif;color:#172033;font-size:10px}h1{font-size:20px;margin:0 0 4px}h2{font-size:14px;margin:20px 0 4px;padding:7px 9px;background:#0f766e;color:#fff}.meta{margin-bottom:16px}.muted{color:#64748b}.summary{display:table;width:100%;table-layout:fixed;border-collapse:separate;border-spacing:8px 0;margin:12px -8px 12px}.box{display:table-cell;width:33.33%;border:1px solid #cbd5e1;padding:8px}.box strong{display:block;font-size:15px;margin-top:3px}table{width:100%;border-collapse:collapse;margin-bottom:13px}th{background:#e2e8f0;text-align:left;font-size:9px}th,td{border:1px solid #cbd5e1;padding:5px;vertical-align:top}.number{text-align:right}.zero{color:#b91c1c}.available{color:#047857;font-weight:bold}.empty{color:#64748b;font-style:italic;padding:7px 2px}
</style></head><body>
<h1>Vaccine Stock Report</h1>
<div class="meta"><strong>Barangay:</strong> {{ $barangay->name }}<br><span class="muted">Generated {{ now()->format('M d, Y h:i A') }}</span></div>
<div class="summary"><div class="box">Vaccines<strong>{{ $vaccineBalances->count() }}</strong></div><div class="box">Total available doses<strong>{{ number_format($vaccineBalances->sum('available_stock')) }}</strong></div><div class="box">Stock items<strong>{{ $items->count() }}</strong></div></div>

@foreach($vaccineBalances as $vaccine)
    @php($vaccineItems = $items->where('vaccine_type_id', $vaccine->id))
    <h2>{{ $loop->iteration }}. {{ $vaccine->name }} — {{ number_format($vaccine->available_stock) }} available doses</h2>
    @if($vaccineItems->isNotEmpty())
        <table><thead><tr><th>Item ID</th><th>Batch number</th><th>Expiry date</th><th>Date received</th><th class="number">Received</th><th class="number">Available</th></tr></thead><tbody>
        @foreach($vaccineItems as $item)
            <tr><td>{{ $item->item_code }}</td><td>{{ $item->batch_number ?? '—' }}</td><td>{{ $item->expiry_date?->format('M d, Y') ?? '—' }}</td><td>{{ $item->received_at?->format('M d, Y') ?? '—' }}</td><td class="number">{{ number_format($item->stock_in ?? 0) }}</td><td class="number available">{{ number_format($item->available_stock) }}</td></tr>
        @endforeach
        </tbody></table>
    @else
        <div class="empty">No stock items recorded for this vaccine.</div>
    @endif
@endforeach
</body></html>
