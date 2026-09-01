<x-layouts::app :title="__('Vaccine Inventory')">
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
        <div class="flex flex-wrap items-center justify-end gap-2">
            <span class="rounded-full border border-teal-500/30 bg-teal-500/10 px-3 py-1.5 text-sm font-medium text-teal-700 dark:text-teal-300">
                {{ auth()->user()->isSuperAdmin() ? ($barangays->firstWhere('id', $selectedBarangay)?->name ?? 'Select barangay') : (auth()->user()->barangay?->name ?? 'Unassigned') }}
            </span>
            <a href="{{ route('vaccine-inventory.csv', auth()->user()->isSuperAdmin() && $selectedBarangay ? ['barangay' => $selectedBarangay] : []) }}" class="app-button-secondary inline-flex items-center gap-2" aria-label="Export inventory data for Excel as CSV">
                <flux:icon.arrow-down-tray class="size-4" />
                <span>Export Excel</span>
            </a>
            <a href="{{ route('vaccine-inventory.report', auth()->user()->isSuperAdmin() && $selectedBarangay ? ['barangay' => $selectedBarangay] : []) }}" class="app-button-secondary inline-flex items-center gap-2" target="_blank" rel="noopener" aria-label="Print inventory report as PDF">
                <flux:icon.printer class="size-4" />
                <span>Print PDF</span>
            </a>
            <a href="{{ route('vaccine-inventory.create') }}" class="app-button-primary inline-flex items-center gap-2" aria-label="Add vaccine stock">
                <flux:icon.plus class="size-4" />
                <span>Add stock</span>
            </a>
        </div>
    </div>

    @if (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin())
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="basis-full">
                <x-location-filters mode="query" :regions="$regions" :provinces="$provinces" :municipalities="$municipalities" :barangays="$barangays" :region-value="$regionFilter" :province-value="$provinceFilter" :municipality-value="$municipalityFilter" :barangay-value="$selectedBarangay ?: 'all'" region-name="region" province-name="province" municipality-name="municipality" barangay-name="barangay" />
            </div>
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
            <h2 class="app-card-title">Deduct stock</h2>
            <p class="mt-1 text-sm text-zinc-500">Choose an existing stock item, then record what reduced its quantity.</p>
        </div>
        <form method="POST" action="{{ route('vaccine-inventory.store') }}" class="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-4" data-inventory-form>
            @csrf
            <input type="hidden" name="barangay_id" value="{{ $selectedBarangay ?: auth()->user()->barangay_id }}">
            <label class="space-y-1.5 md:col-span-2"><span class="text-sm font-medium">Existing stock item</span><div class="relative" data-searchable-item><input type="search" class="app-input" placeholder="Search Item ID, vaccine, or batch..." autocomplete="off" data-item-search required><input type="hidden" name="vaccine_inventory_item_id" value="{{ old('vaccine_inventory_item_id') }}" data-item-value><div class="absolute z-10 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900" data-item-options>@foreach ($inventoryItems as $item)<button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" data-item-option data-id="{{ $item->id }}" data-label="{{ $item->item_code }} — {{ $item->vaccineType->name }}{{ $item->batch_number ? ' | Batch '.$item->batch_number : '' }}">{{ $item->item_code }} — {{ $item->vaccineType->name }} <span class="text-zinc-500">({{ $item->available_stock }} doses{{ $item->batch_number ? ', batch '.$item->batch_number : '' }})</span></button>@endforeach</div></div><span class="text-xs text-zinc-500">Search and select the stock batch to deduct from.</span></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Why deduct?</span><select name="transaction_type" class="app-input" required>@foreach ($types as $value => $label) @if ($value !== 'receipt')<option value="{{ $value }}" @selected(old('transaction_type', 'usage') === $value)>{{ $label }}</option>@endif @endforeach</select></label>
            <input type="hidden" name="movement" value="out">
            <label class="space-y-1.5"><span class="text-sm font-medium">Quantity (doses)</span><input type="number" min="1" name="quantity" value="{{ old('quantity') }}" class="app-input" required></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Transaction date</span><input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="app-input" required></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Reference number</span><input name="reference_number" value="{{ old('reference_number') }}" class="app-input"></label>
            <label class="space-y-1.5 md:col-span-2"><span class="text-sm font-medium">Notes</span><textarea name="notes" class="app-input" rows="2">{{ old('notes') }}</textarea></label>
            <div></div>
            <div class="flex items-end justify-end"><button class="app-button-primary">Deduct stock</button></div>
        </form>
        <div class="mx-5 mb-5 rounded-lg bg-slate-50 p-3 text-sm text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">
            <strong>Need to correct an old entry?</strong> Choose <strong>Stock adjustment</strong> and enter the quantity to deduct. Stock history is preserved.
        </div>
        @if ($errors->any())<div class="px-5 pb-5 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </section>

    <section class="app-card">
        <div class="app-card-header"><h2 class="app-card-title">Transaction history</h2></div>
        <div class="overflow-x-auto"><table class="app-table"><thead><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Item ID</th><th class="px-4 py-3">Vaccine</th><th class="px-4 py-3">Transaction</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Batch / expiry</th><th class="px-4 py-3">Recorded by</th></tr></thead><tbody>
            @forelse ($transactions as $transaction)
                <tr class="app-table-row"><td>{{ $transaction->transaction_date->format('M d, Y') }}</td><td class="font-medium">{{ $transaction->inventoryItem?->item_code ?? 'Legacy entry' }}</td><td class="font-medium">{{ $transaction->vaccineType->name }}</td><td>{{ $types[$transaction->transaction_type] ?? ucfirst($transaction->transaction_type) }}</td><td class="{{ $transaction->movement === 'out' ? 'text-red-600' : 'text-emerald-600' }}">{{ $transaction->movement === 'out' ? '-' : '+' }}{{ number_format($transaction->quantity) }}</td><td>{{ $transaction->inventoryItem?->batch_number ?? $transaction->batch_number ?? '—' }} @if($transaction->inventoryItem?->expiry_date ?? $transaction->expiry_date)<div class="text-xs text-zinc-500">{{ ($transaction->inventoryItem?->expiry_date ?? $transaction->expiry_date)->format('M d, Y') }}</div>@endif</td><td>{{ $transaction->recorder->name }}</td></tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No inventory transactions recorded yet.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="p-5">{{ $transactions->links() }}</div>
    </section>
</div>

<script>
    (() => {
        const form = document.querySelector('[data-inventory-form]');
        if (!form) return;

        const search = form.querySelector('[data-item-search]');
        const hidden = form.querySelector('[data-item-value]');
        const options = form.querySelector('[data-item-options]');
        const items = [...form.querySelectorAll('[data-item-option]')];
        const selected = items.find((option) => option.dataset.id === hidden.value);
        if (selected) search.value = selected.dataset.label;
        const filter = () => {
            const query = search.value.toLowerCase().trim();
            options.classList.remove('hidden');
            items.forEach((option) => { option.classList.toggle('hidden', query !== '' && !option.dataset.label.toLowerCase().includes(query)); });
        };
        search.addEventListener('focus', filter);
        search.addEventListener('input', () => { hidden.value = ''; filter(); });
        items.forEach((option) => option.addEventListener('click', () => { hidden.value = option.dataset.id; search.value = option.dataset.label; options.classList.add('hidden'); }));
        document.addEventListener('click', (event) => { if (!event.target.closest('[data-searchable-item]')) options.classList.add('hidden'); });
    })();
</script>
</x-layouts::app>
