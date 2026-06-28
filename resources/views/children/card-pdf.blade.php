<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $child->full_name }} vaccine card</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 24px; }
        .header { display: flex; justify-content: space-between; gap: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        th { background: #f8fafc; }
        img { width: 170px; height: 170px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <h1>Digital Child Vaccine Card</h1>
                <p><strong>{{ $child->full_name }}</strong></p>
                <p>{{ ucfirst($child->sex) }} | {{ $child->birthdate->format('M d, Y') }} | {{ $child->barangay?->name }}</p>
                <p>Validation URL: {{ $validationUrl }}</p>
            </div>
            <div>
                <img src="{{ $qrCode }}" alt="QR code">
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>{{ $record->vaccineType->name }}</td>
                        <td>{{ $record->dose_number ?: 'Not set' }}</td>
                        <td>{{ $record->administered_at->format('M d, Y') }}</td>
                        <td>{{ ucfirst($record->verification_status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No vaccine records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
