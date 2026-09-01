<div class="app-page">
    @if (session('status'))
        <div class="app-alert-success">{{ session('status') }}</div>
    @endif

    <div class="page-heading">
        <div>
            <p class="eyebrow">INVENTORY</p>
            <h1 class="page-title">Vaccine inventory</h1>
            <p class="page-subtitle">Track vaccine receipts, usage, losses, adjustments, and available stock.</p>
        </div>
    </div>

    @if ($barangays->isNotEmpty())
        <form method="GET" class="app-card flex flex-wrap items-end gap-3 p-4">
            <label class="min-w-64 space-y-1.5">
                <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Barangay</span>
                <select name="barangay" class="app-input">
                    <option value="">Select a barangay</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) $selectedBarangay === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="app-button-secondary">View inventory</button>
        </form>
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        @forelse ($balances as $vaccine)
            <section class="app-card p-5">
                <p class="text-sm text-zinc-500">{{ $vaccine->name }}</p>
                <p class="mt-2 text-3xl font-semibold {{ $vaccine->available_stock <= 0 ? 'text-red-600' : 'text-slate-950 dark:text-white' }}">{{ number_format($vaccine->available_stock) }}</p>
                <p class="mt-1 text-xs text-zinc-500">doses available</p>
            </section>
        @empty
            <section class="app-card p-5 text-sm text-zinc-500 md:col-span-2 lg:col-span-4">Select a barangay to view stock balances.</section>
        @endforelse
    </div>

    <section class="app-card">
        <div class="app-card-header">
            <h2 class="app-card-title">Record inventory transaction</h2>
        </div>
        <form method="POST" action="{{ route('vaccine-inventory.store') }}" class="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-4">
            @csrf
            @if (auth()->user()->isSuperAdmin())
                <label class="space-y-1.5"><span class="text-sm font-medium">Barangay</span><select name="barangay_id" class="app-input" required><option value="">Select barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string) old('barangay_id', $selectedBarangay) === (string) $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></label>
            @else
                <input type="hidden" name="barangay_id" value="{{ auth()->user()->barangay_id }}">
                <div><span class="text-sm font-medium">Barangay</span><p class="mt-2 text-sm text-zinc-600">{{ auth()->user()->barangay?->name ?? 'Unassigned' }}</p></div>
            @endif
            <label class="space-y-1.5"><span class="text-sm font-medium">Vaccine</span><select name="vaccine_type_id" class="app-input" required><option value="">Select vaccine</option>@foreach ($vaccines as $vaccine)<option value="{{ $vaccine->id }}" @selected(old('vaccine_type_id') == $vaccine->id)>{{ $vaccine->name }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Transaction</span><select name="transaction_type" class="app-input" required>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('transaction_type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Movement</span><select name="movement" class="app-input" required><option value="in" @selected(old('movement') === 'in')>Add stock</option><option value="out" @selected(old('movement') === 'out')>Remove stock</option></select></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Quantity (doses)</span><input type="number" min="1" name="quantity" value="{{ old('quantity') }}" class="app-input" required></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Transaction date</span><input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="app-input" required></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Batch number</span><input name="batch_number" value="{{ old('batch_number') }}" class="app-input"></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Expiry date</span><input type="date" name="expiry_date" value="{{ old('expiry_date') }}" class="app-input"></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Reference number</span><input name="reference_number" value="{{ old('reference_number') }}" class="app-input"></label>
            <label class="space-y-1.5 md:col-span-2"><span class="text-sm font-medium">Notes</span><textarea name="notes" class="app-input" rows="2">{{ old('notes') }}</textarea></label>
            <div class="flex items-end"><button class="app-button-primary">Save transaction</button></div>
        </form>
        @if ($errors->any())<div class="px-5 pb-5 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </section>

    <section class="app-card">
        <div class="app-card-header"><h2 class="app-card-title">Transaction history</h2></div>
        <div class="overflow-x-auto"><table class="app-table"><thead><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Barangay</th><th class="px-4 py-3">Vaccine</th><th class="px-4 py-3">Transaction</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Batch / expiry</th><th class="px-4 py-3">Recorded by</th></tr></thead><tbody>
            @forelse ($transactions as $transaction)
                <tr class="app-table-row"><td>{{ $transaction->transaction_date->format('M d, Y') }}</td><td>{{ $transaction->barangay->name }}</td><td class="font-medium">{{ $transaction->vaccineType->name }}</td><td>{{ $types[$transaction->transaction_type] ?? ucfirst($transaction->transaction_type) }}</td><td class="{{ $transaction->movement === 'out' ? 'text-red-600' : 'text-emerald-600' }}">{{ $transaction->movement === 'out' ? '-' : '+' }}{{ number_format($transaction->quantity) }}</td><td>{{ $transaction->batch_number ?? '—' }} @if($transaction->expiry_date)<div class="text-xs text-zinc-500">{{ $transaction->expiry_date->format('M d, Y') }}</div>@endif</td><td>{{ $transaction->recorder->name }}</td></tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No inventory transactions recorded yet.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="p-5">{{ $transactions->links() }}</div>
    </section>
</div>
