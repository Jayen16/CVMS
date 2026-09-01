<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#172033}h1{font-size:22px}table{border-collapse:collapse;width:100%}td{border:1px solid #d6dde8;padding:8px}</style></head><body>
<h1>{{ $barangay->name }} vaccination report</h1>
<p>Generated {{ $generatedAt->format('M d, Y h:i A') }}</p>
<table><tr><td>Children</td><td>{{ $children }}</td></tr><tr><td>Vaccination records</td><td>{{ $vaccinations }}</td></tr></table>
</body></html>
