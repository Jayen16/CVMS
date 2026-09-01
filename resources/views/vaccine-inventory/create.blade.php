<x-layouts::app :title="__('Add Vaccine Stock')">
<div class="app-page" data-stock-basket>
    <div class="page-heading">
        <div>
            <a href="{{ route('vaccine-inventory.index') }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to inventory</a>
            <p class="eyebrow mt-4">INVENTORY</p>
            <h1 class="page-title">Add stock</h1>
            <p class="page-subtitle">Add one or more deliveries below. They will not be recorded until you click Save stock.</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if (auth()->user()->isSuperAdmin())
                <label class="flex items-center gap-2 text-sm"><span class="sr-only">Barangay</span><select class="app-input min-w-56" data-barangay required><option value="">Select barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}">{{ $barangay->name }}</option>@endforeach</select></label>
            @else
                <span class="rounded-full border border-teal-500/30 bg-teal-500/10 px-3 py-1.5 text-sm font-medium text-teal-700 dark:text-teal-300">{{ auth()->user()->barangay?->name ?? 'Unassigned' }}</span>
                <input type="hidden" value="{{ auth()->user()->barangay_id }}" data-barangay>
            @endif
        </div>
    </div>

    <section class="app-card">
        <div class="app-card-header"><h2 class="app-card-title">Stock details</h2><p class="mt-1 text-sm text-zinc-500">Add a separate row for each vaccine batch or delivery.</p></div>
        <div class="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-1.5"><span class="text-sm font-medium">Vaccine</span><select class="app-input" data-vaccine required><option value="">Select vaccine</option>@foreach ($vaccines as $vaccine)<option value="{{ $vaccine->id }}">{{ $vaccine->name }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Quantity (doses)</span><input type="number" min="1" class="app-input" data-quantity required></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Date received</span><input type="date" value="{{ now()->toDateString() }}" class="app-input" data-date required></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Batch number</span><input class="app-input" data-batch></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Expiry date</span><input type="date" class="app-input" data-expiry></label>
            <label class="space-y-1.5"><span class="text-sm font-medium">Reference number</span><input class="app-input" data-reference></label>
            <label class="space-y-1.5 md:col-span-2 lg:col-span-4"><span class="text-sm font-medium">Notes</span><textarea class="app-input" rows="2" data-notes></textarea></label>
            <div class="flex justify-end md:col-span-2 lg:col-span-4"><button type="button" class="app-button-secondary" data-add-row>Add to pending stock</button></div>
        </div>
    </section>

    <form method="POST" action="{{ route('vaccine-inventory.store') }}" data-save-form>
        @csrf
        <input type="hidden" name="transaction_type" value="receipt">
        <input type="hidden" name="movement" value="in">
        <input type="hidden" name="barangay_id" data-save-barangay>
        <section class="app-card">
            <div class="app-card-header"><h2 class="app-card-title">Pending stock</h2><p class="mt-1 text-sm text-zinc-500">These entries are temporary. Remove any row before saving.</p></div>
            <div class="overflow-x-auto"><table class="app-table"><thead><tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Vaccine</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Batch / expiry</th><th class="px-4 py-3">Date received</th><th class="px-4 py-3">Action</th></tr></thead><tbody data-pending-rows><tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No pending stock. Add details above.</td></tr></tbody></table></div>
            <div class="flex items-center justify-between gap-3 border-t border-slate-200 p-5 dark:border-zinc-800"><span class="text-sm text-zinc-500" data-pending-count>0 pending items</span><button class="app-button-primary" data-save-stock disabled>Save stock</button></div>
        </section>
    </form>
    @if ($errors->any())<div class="app-alert-error">{{ $errors->first() }}</div>@endif
</div>

<script>
    (() => {
        const page = document.querySelector('[data-stock-basket]');
        if (!page) return;
        const form = page.querySelector('[data-save-form]');
        const rows = page.querySelector('[data-pending-rows]');
        const pendingCount = page.querySelector('[data-pending-count]');
        const saveButton = page.querySelector('[data-save-stock]');
        const pending = [];
        const field = (name) => page.querySelector(`[data-${name}]`);
        const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

        const render = () => {
            rows.innerHTML = pending.length ? pending.map((item, index) => `<tr class="app-table-row"><td>${index + 1}</td><td class="font-medium">${escape(item.vaccineName)}</td><td>${escape(item.quantity)} doses</td><td>${escape(item.batch || '—')}${item.expiry ? `<div class="text-xs text-zinc-500">${escape(item.expiry)}</div>` : ''}</td><td>${escape(item.date)}</td><td><button type="button" class="text-sm font-medium text-red-600 hover:underline" data-remove-row="${index}">Remove</button></td></tr>`).join('') : '<tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No pending stock. Add details above.</td></tr>';
            pendingCount.textContent = `${pending.length} pending item${pending.length === 1 ? '' : 's'}`;
            saveButton.disabled = pending.length === 0;
            rows.querySelectorAll('[data-remove-row]').forEach((button) => button.addEventListener('click', () => { pending.splice(Number(button.dataset.removeRow), 1); render(); }));
        };

        page.querySelector('[data-add-row]').addEventListener('click', () => {
            const vaccine = field('vaccine');
            const quantity = field('quantity');
            if (!vaccine.value || !quantity.value || Number(quantity.value) < 1 || !field('date').value) { vaccine.reportValidity(); quantity.reportValidity(); return; }
            pending.push({ vaccineId: vaccine.value, vaccineName: vaccine.options[vaccine.selectedIndex].text, quantity: quantity.value, date: field('date').value, batch: field('batch').value, expiry: field('expiry').value, reference: field('reference').value, notes: field('notes').value });
            vaccine.value = ''; quantity.value = ''; field('batch').value = ''; field('expiry').value = ''; field('reference').value = ''; field('notes').value = '';
            render();
        });

        form.addEventListener('submit', (event) => {
            if (!pending.length || !field('barangay').value) { event.preventDefault(); return; }
            page.querySelector('[data-save-barangay]').value = field('barangay').value;
            pending.forEach((item, index) => Object.entries({ vaccine_type_id: item.vaccineId, quantity: item.quantity, transaction_date: item.date, batch_number: item.batch, expiry_date: item.expiry, reference_number: item.reference, notes: item.notes }).forEach(([name, value]) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = `stocks[${index}][${name}]`; input.value = value; form.appendChild(input); }));
        });
    })();
</script>
</x-layouts::app>
