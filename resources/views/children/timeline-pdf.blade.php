<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vaccination Timeline</title>
    <style>
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.35; }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 20px; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eef4f3; color: #334155; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #d6dde8; padding: 6px 7px; vertical-align: top; }
        .header { border-bottom: 2px solid #0f766e; margin-bottom: 12px; padding-bottom: 10px; }
        .muted { color: #64748b; }
        .badge { text-transform: capitalize; }
        .section { margin-top: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $child->full_name }} vaccination timeline</h1>
        <p class="muted">
            {{ ucfirst($child->sex) }} | Born {{ $child->birthdate->format('M d, Y') }} | {{ $child->barangay?->name }}
            | Generated {{ $generatedAt->format('M d, Y h:i A') }}
        </p>
        <p class="muted">
            Vaccine filter:
            {{ $selectedVaccine !== '' ? strtoupper($selectedVaccine) : 'All configured vaccines' }}
        </p>
    </div>

    @forelse ($timeline as $row)
        <div class="section">
            <h2>{{ $row['name'] }}</h2>
            <p class="muted">
                {{ $row['indication_label'] }}@if($row['version_name']) | {{ $row['version_name'] }}@endif
            </p>
            <table style="margin-top: 8px;">
                <thead>
                    <tr>
                        <th>Dose</th>
                        <th>Schedule age</th>
                        <th>Due date</th>
                        <th>Status</th>
                        <th>Date given</th>
                        <th>Action date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($row['doses'] as $point)
                        <tr>
                            <td> Dose {{ $point['dose'] }}: {{ $point['label'] }}</td>
                            <td>{{ $point['age_summary'] }}</td>
                            <td>{{ $point['due_at']->format('M d, Y') }}</td>
                            <td class="badge">{{ str_replace('_', ' ', $point['status']) }}</td>
                            <td>{{ $point['record']?->administered_at?->format('M d, Y') ?? 'Not yet given' }}</td>
                            <td>{{ $point['action_at']?->format('M d, Y') ?? 'Done' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p>No configured schedule rows found.</p>
    @endforelse
</body>
</html>
