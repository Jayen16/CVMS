<x-layouts::app :title="__('Vaccine Inventory')">
<div class="app-page" x-data="{ locationLoading: false }">
    <div x-show="locationLoading" x-cloak class="fixed inset-x-0 top-0 z-[60] flex items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
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
                {{ $barangays->firstWhere('id', $selectedBarangay)?->name ?? (auth()->user()->isMunicipalAdmin() ? 'Select barangay' : (auth()->user()->barangay?->name ?? 'Unassigned')) }}
            </span>
            @if (! $requiresLocationSelection)
            <a href="{{ route('vaccine-inventory.csv', (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin()) && $selectedBarangay ? ['barangay' => $selectedBarangay] : []) }}" class="app-button-secondary inline-flex items-center gap-2" aria-label="Export inventory data for Excel as CSV">
                <flux:icon.arrow-down-tray class="size-4" />
                <span>Export Excel</span>
            </a>
            <a href="{{ route('vaccine-inventory.report', (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin()) && $selectedBarangay ? ['barangay' => $selectedBarangay] : []) }}" class="app-button-secondary inline-flex items-center gap-2" target="_blank" rel="noopener" aria-label="Print inventory report as PDF">
                <flux:icon.printer class="size-4" />
                <span>Print PDF</span>
            </a>
            @if (auth()->user()->canManageInventory())
            <a href="{{ route('vaccine-inventory.create') }}" class="app-button-primary inline-flex items-center gap-2" aria-label="Add vaccine stock">
                <flux:icon.plus class="size-4" />
                <span>Add stock</span>
            </a>
            @endif
            @endif
        </div>
    </div>

    @if (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin())
        <form method="GET" class="flex flex-wrap items-end gap-3" @submit="locationLoading = true">
            <div class="basis-full">
                <x-location-filters mode="query" :regions="$regions" :provinces="$provinces" :municipalities="$municipalities" :barangays="$barangays" :region-value="$regionFilter" :province-value="$provinceFilter" :municipality-value="$municipalityFilter" :barangay-value="$selectedBarangay ?: 'all'" region-name="region" province-name="province" municipality-name="municipality" barangay-name="barangay" />
            </div>
            <button class="app-button-secondary">View inventory</button>
        </form>
    @endif

    @if ($requiresLocationSelection)
        <section class="app-card p-8 text-center">
            <h2 class="app-card-title">Select a region to view vaccine inventory</h2>
            <p class="mt-2 text-sm text-zinc-500">Choose a location above to load stock balances and transaction history.</p>
        </section>
    @else
    <section class="app-card overflow-hidden">
        <div class="app-card-header">
            <h2 class="app-card-title">Available vaccine doses</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>Vaccine name</th><th>Available dose</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($balances as $vaccine)
                        <tr class="app-table-row">
                            <td class="font-medium text-slate-900 dark:text-white">{{ $vaccine->name }}</td>
                            <td class="font-semibold {{ $vaccine->available_stock <= 0 ? 'text-red-600' : 'text-slate-900 dark:text-white' }}">{{ number_format($vaccine->available_stock) }}</td>
                            <td><span class="status-pill {{ $vaccine->available_stock > 0 ? 'status-verified' : 'status-rejected' }}">{{ $vaccine->available_stock > 0 ? 'In stock' : 'Out of stock' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-500">Select a barangay to view stock balances.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if (auth()->user()->canManageInventory())
    <section class="app-card" x-data="{ deductStockOpen: @js($errors->hasAny(['vaccine_inventory_item_id', 'transaction_type', 'quantity', 'transaction_date', 'reference_number', 'notes'])) }">
        <div class="app-card-header flex items-center justify-between gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100 dark:bg-rose-950/50 dark:text-rose-300 dark:ring-rose-900/70" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 5-5m-5 5-5-5" /><path d="M5 17v3h14v-3" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Stock outflow</p>
                <h2 class="app-card-title mt-0.5">Deduct stock</h2>
                <p class="mt-1 text-sm text-zinc-500">Select a batch, enter the quantity, and record the reason.</p>
            </div>
            <button type="button" class="app-button-secondary shrink-0" @click="deductStockOpen = !deductStockOpen" :aria-expanded="deductStockOpen.toString()" aria-controls="deduct-stock-fields" x-text="deductStockOpen ? '− Hide' : '+ Deduct stock'">+ Deduct stock</button>
        </div>
        <form id="deduct-stock-fields" x-show="deductStockOpen" x-cloak method="POST" action="{{ route('vaccine-inventory.store') }}" class="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-12" data-inventory-form>
            @csrf
            <input type="hidden" name="barangay_id" value="{{ $selectedBarangay ?: auth()->user()->barangay_id }}">
            <label class="space-y-1.5 md:col-span-2 lg:col-span-4"><span class="text-sm font-medium">Existing stock item</span><div class="relative" data-searchable-item><input type="search" class="app-input" placeholder="Search Item ID, vaccine, or batch..." autocomplete="off" data-item-search required><input type="hidden" name="vaccine_inventory_item_id" value="{{ old('vaccine_inventory_item_id') }}" data-item-value><div class="absolute z-10 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900" data-item-options>@foreach ($inventoryItems as $item)<button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" data-item-option data-id="{{ $item->id }}" data-label="{{ $item->item_code }} — {{ $item->vaccineType->name }}{{ $item->batch_number ? ' | Batch '.$item->batch_number : '' }}">{{ $item->item_code }} — {{ $item->vaccineType->name }} <span class="text-zinc-500">({{ $item->available_stock }} doses{{ $item->batch_number ? ', batch '.$item->batch_number : '' }})</span></button>@endforeach</div></div><span class="text-xs text-zinc-500">Search and select the stock batch to deduct from.</span></label>
            <label class="space-y-1.5 lg:col-span-3"><span class="text-sm font-medium">Why deduct?</span><select name="transaction_type" class="app-input" required>@foreach ($types as $value => $label) @if ($value !== 'receipt')<option value="{{ $value }}" @selected(old('transaction_type', 'usage') === $value)>{{ $label }}</option>@endif @endforeach</select></label>
            <input type="hidden" name="movement" value="out">
            <label class="space-y-1.5 lg:col-span-2"><span class="text-sm font-medium">Quantity (doses)</span><input type="number" min="1" name="quantity" value="{{ old('quantity') }}" class="app-input" required></label>
            <label class="space-y-1.5 lg:col-span-3"><span class="text-sm font-medium">Transaction date</span><input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="app-input" required></label>
            <label class="space-y-1.5 lg:col-span-3 lg:col-start-1"><span class="text-sm font-medium">Reference number</span><input name="reference_number" value="{{ old('reference_number') }}" class="app-input"></label>
            <label class="space-y-1.5 md:col-span-2 lg:col-span-12"><span class="text-sm font-medium">Notes</span><textarea name="notes" class="app-input" rows="2">{{ old('notes') }}</textarea></label>
            <div class="flex justify-end border-t border-slate-100 pt-4 dark:border-zinc-800 md:col-span-2 lg:col-span-12"><button class="app-button-primary w-full justify-center md:w-auto">Deduct stock</button></div>
        </form>
        <div x-show="deductStockOpen" x-cloak class="mx-5 mb-5 rounded-xl border border-sky-100 bg-sky-50/70 p-3 text-sm text-sky-900 dark:border-sky-950 dark:bg-sky-950/30 dark:text-sky-200">
            <strong>Need to correct an old entry?</strong> Choose <strong>Stock adjustment</strong> and enter the quantity to deduct. Stock history is preserved.
        </div>
        @if ($errors->any())<div x-show="deductStockOpen" x-cloak class="px-5 pb-5 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </section>
    @endif

    <section class="app-card">
        <div class="app-card-header flex flex-wrap items-center justify-between gap-3">
            <h2 class="app-card-title">Transaction history</h2>
            <label class="flex items-center gap-2 text-sm text-zinc-500">
                <span>Rows per page</span>
                <select class="app-input w-auto py-1.5" data-transaction-page-size aria-label="Rows per page">
                    @foreach ([10, 25, 50, 100] as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="grid gap-3 p-3 md:hidden">
            @forelse ($transactions as $transaction)
                <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="font-semibold text-slate-900 dark:text-white">{{ $transaction->vaccineType->name }}</h3><p class="mt-0.5 text-xs text-zinc-500">{{ $transaction->inventoryItem?->item_code ?? 'Legacy entry' }}</p></div><span class="shrink-0 text-sm text-zinc-500">{{ $transaction->transaction_date->format('M d, Y') }}</span></div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                        <div><dt class="text-xs text-zinc-500">Transaction</dt><dd class="mt-0.5">{{ $types[$transaction->transaction_type] ?? ucfirst($transaction->transaction_type) }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Quantity</dt><dd class="mt-0.5 font-semibold {{ $transaction->movement === 'out' ? 'text-red-600' : 'text-emerald-600' }}">{{ $transaction->movement === 'out' ? '-' : '+' }}{{ number_format($transaction->quantity) }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Batch</dt><dd class="mt-0.5">{{ $transaction->inventoryItem?->batch_number ?? $transaction->batch_number ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">Expiry</dt><dd class="mt-0.5">{{ ($transaction->inventoryItem?->expiry_date ?? $transaction->expiry_date)?->format('M d, Y') ?? '—' }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-zinc-500">Recorded by</dt><dd class="mt-0.5">{{ $transaction->recorder->name }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="app-card p-6 text-center text-sm text-zinc-500">No inventory transactions recorded yet.</div>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block"><table class="app-table"><thead><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Item ID</th><th class="px-4 py-3">Vaccine</th><th class="px-4 py-3">Transaction</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Batch / expiry</th><th class="px-4 py-3">Recorded by</th></tr></thead><tbody>
            @forelse ($transactions as $transaction)
                <tr class="app-table-row"><td>{{ $transaction->transaction_date->format('M d, Y') }}</td><td class="font-medium">{{ $transaction->inventoryItem?->item_code ?? 'Legacy entry' }}</td><td class="font-medium">{{ $transaction->vaccineType->name }}</td><td>{{ $types[$transaction->transaction_type] ?? ucfirst($transaction->transaction_type) }}</td><td class="{{ $transaction->movement === 'out' ? 'text-red-600' : 'text-emerald-600' }}">{{ $transaction->movement === 'out' ? '-' : '+' }}{{ number_format($transaction->quantity) }}</td><td>{{ $transaction->inventoryItem?->batch_number ?? $transaction->batch_number ?? '—' }} @if($transaction->inventoryItem?->expiry_date ?? $transaction->expiry_date)<div class="text-xs text-zinc-500">{{ ($transaction->inventoryItem?->expiry_date ?? $transaction->expiry_date)->format('M d, Y') }}</div>@endif</td><td>{{ $transaction->recorder->name }}</td></tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No inventory transactions recorded yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @if ($transactions->hasPages())
            <div class="border-t border-slate-200 p-5 dark:border-zinc-700">{{ $transactions->links() }}</div>
        @endif
    </section>
    @endif
</div>

<script>
    const transactionPageSize = document.querySelector('[data-transaction-page-size]');
    transactionPageSize?.addEventListener('change', (event) => {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', event.target.value);
        url.searchParams.delete('page');
        window.location.assign(url);
    });

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
