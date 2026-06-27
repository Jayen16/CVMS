<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vaccination Report</title>
    <style>
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.35; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 22px; }
        h2 { border-bottom: 1px solid #d6dde8; font-size: 13px; margin: 18px 0 8px; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eef4f3; color: #334155; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #d6dde8; padding: 6px 7px; vertical-align: top; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #0f766e; margin-bottom: 12px; padding-bottom: 10px; }
        .stats { display: table; margin-top: 12px; table-layout: fixed; width: 100%; }
        .stat { border: 1px solid #d6dde8; display: table-cell; padding: 8px; }
        .stat-label { color: #64748b; font-size: 9px; text-transform: uppercase; }
        .stat-value { color: #0f172a; font-size: 17px; font-weight: bold; margin-top: 3px; }
        .grid { display: table; table-layout: fixed; width: 100%; }
        .col { display: table-cell; padding-right: 10px; vertical-align: top; width: 50%; }
        .col:last-child { padding-right: 0; }
        .badge { text-transform: capitalize; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Vaccination report</h1>
        <p class="muted">
            Period: {{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}
            | Generated: {{ $generatedAt->format('M d, Y h:i A') }}
        </p>
    </div>

    <div class="stats">
        <div class="stat"><div class="stat-label">Barangays</div><div class="stat-value">{{ $stats['barangays'] }}</div></div>
        <div class="stat"><div class="stat-label">Nurses</div><div class="stat-value">{{ $stats['nurses'] }}</div></div>
        <div class="stat"><div class="stat-label">Children</div><div class="stat-value">{{ $stats['children'] }}</div></div>
        <div class="stat"><div class="stat-label">Vaccinations</div><div class="stat-value">{{ $stats['vaccinations'] }}</div></div>
        <div class="stat"><div class="stat-label">Pending review</div><div class="stat-value">{{ $stats['pending'] }}</div></div>
    </div>

    <div class="grid">
        <div class="col">
            <h2>Barangay coverage</h2>
            <table>
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Nurses</th>
                        <th>Children</th>
                        <th>Vaccinations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($barangays as $barangay)
                        <tr>
                            <td>{{ $barangay->name }}</td>
                            <td>{{ $barangay->nurses_count }}</td>
                            <td>{{ $barangay->children_count }}</td>
                            <td>{{ $barangay->report_vaccinations_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No barangays yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="col">
            <h2>Vaccines administered</h2>
            <table>
                <thead>
                    <tr>
                        <th>Vaccine</th>
                        <th>Code</th>
                        <th>Records</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vaccines as $vaccine)
                        <tr>
                            <td>{{ $vaccine->name }}</td>
                            <td>{{ strtoupper($vaccine->code) }}</td>
                            <td>{{ $vaccine->report_records_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No vaccines yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid">
        <div class="col">
            <h2>Verification status</h2>
            <table>
                <tbody>
                    @forelse ($verificationCounts as $status => $total)
                        <tr>
                            <td class="badge">{{ str_replace('_', ' ', $status) }}</td>
                            <td>{{ $total }}</td>
                        </tr>
                    @empty
                        <tr><td>No vaccination records in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="col">
            <h2>Record source</h2>
            <table>
                <tbody>
                    @forelse ($sourceCounts as $source => $total)
                        <tr>
                            <td class="badge">{{ str_replace('_', ' ', $source) }}</td>
                            <td>{{ $total }}</td>
                        </tr>
                    @empty
                        <tr><td>No vaccination records in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2>Recent vaccination records</h2>
    <table>
        <thead>
            <tr>
                <th>Child</th>
                <th>Barangay</th>
                <th>Vaccine</th>
                <th>Dose</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentRecords as $record)
                <tr>
                    <td>{{ $record->child?->full_name }}</td>
                    <td>{{ $record->child?->barangay?->name ?? 'Unassigned' }}</td>
                    <td>{{ $record->vaccineType?->name }}</td>
                    <td>{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }}</td>
                    <td>{{ $record->administered_at?->format('M d, Y') }}</td>
                    <td class="badge">{{ str_replace('_', ' ', $record->verification_status) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No records in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
