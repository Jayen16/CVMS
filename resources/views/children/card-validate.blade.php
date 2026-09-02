<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $child->full_name }} vaccine card validation</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 900px; margin: 0 auto; padding: 32px 16px; }
        .card { background: white; border: 1px solid #dbeafe; border-radius: 18px; padding: 24px; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Vaccine card validation</h1>
            <p><strong>{{ $child->full_name }}</strong></p>
            <p>{{ ucfirst($child->sex) }} | {{ $child->birthdate->format('M d, Y') }}</p>
            <p>{{ $child->barangay?->municipalityRelation?->province?->name ?? 'N/A' }} · {{ $child->barangay?->municipalityRelation?->name ?? 'N/A' }} · {{ $child->barangay?->name ?? 'N/A' }}</p>
            <p>This page was opened from the QR-enabled digital vaccine card.</p>
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
                        <tr><td colspan="4">No vaccine records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
