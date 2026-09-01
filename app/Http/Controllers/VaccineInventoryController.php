<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\User;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineType;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\LaravelPdf\Facades\Pdf;

class VaccineInventoryController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $this->authorizeInventory($user);

        $selectedBarangay = $user->isSuperAdmin()
            ? request()->string('barangay')->toString()
            : (string) $user->barangay_id;

        $transactions = VaccineInventoryTransaction::query()
            ->forUser($user)
            ->when($selectedBarangay !== '', fn ($query) => $query->where('barangay_id', $selectedBarangay))
            ->with(['barangay', 'vaccineType', 'inventoryItem', 'recorder'])
            ->latest('transaction_date')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $balances = VaccineType::query()
            ->where('active', true)
            ->withSum([
                'inventoryTransactions as stock_in' => fn ($builder) => $builder
                    ->when($selectedBarangay !== '', fn ($inner) => $inner->where('barangay_id', $selectedBarangay))
                    ->where('movement', 'in'),
            ], 'quantity')->withSum([
                'inventoryTransactions as stock_out' => fn ($builder) => $builder
                    ->when($selectedBarangay !== '', fn ($inner) => $inner->where('barangay_id', $selectedBarangay))
                    ->where('movement', 'out'),
            ], 'quantity')
            ->orderBy('name')
            ->get()
            ->map(function (VaccineType $vaccine): VaccineType {
                $vaccine->available_stock = (int) ($vaccine->stock_in ?? 0) - (int) ($vaccine->stock_out ?? 0);

                return $vaccine;
            });

        return view('vaccine-inventory.index', [
            'transactions' => $transactions,
            'balances' => $balances,
            'barangays' => $user->isSuperAdmin() ? Barangay::orderBy('name')->get() : collect(),
            'selectedBarangay' => $selectedBarangay,
            'types' => VaccineInventoryTransaction::typeOptions(),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'inventoryItems' => $this->inventoryItems($selectedBarangay),
        ]);
    }

    public function report(Request $request)
    {
        $user = auth()->user();
        $this->authorizeInventory($user);
        $validated = $request->validate(['barangay' => ['nullable', 'exists:barangays,id']]);
        $barangayId = $user->isSuperAdmin() ? ($validated['barangay'] ?? null) : $user->barangay_id;

        if (! $barangayId) {
            return back()->withErrors(['barangay' => 'Select a barangay before printing the inventory report.']);
        }

        $barangay = Barangay::findOrFail($barangayId);
        AuditLog::recordAction('printed', 'Printed vaccine inventory report', $barangay, ['format' => 'pdf']);
        $items = VaccineInventoryItem::query()
            ->where('barangay_id', $barangayId)
            ->with('vaccineType')
            ->withSum(['transactions as stock_in' => fn ($query) => $query->where('movement', 'in')], 'quantity')
            ->withSum(['transactions as stock_out' => fn ($query) => $query->where('movement', 'out')], 'quantity')
            ->orderBy('expiry_date')
            ->orderBy('item_code')
            ->get()
            ->map(function (VaccineInventoryItem $item): VaccineInventoryItem {
                $item->available_stock = (int) ($item->stock_in ?? 0) - (int) ($item->stock_out ?? 0);

                return $item;
            });
        $vaccineBalances = VaccineType::query()
            ->where('active', true)
            ->withSum([
                'inventoryTransactions as stock_in' => fn ($query) => $query
                    ->where('barangay_id', $barangayId)
                    ->where('movement', 'in'),
            ], 'quantity')
            ->withSum([
                'inventoryTransactions as stock_out' => fn ($query) => $query
                    ->where('barangay_id', $barangayId)
                    ->where('movement', 'out'),
            ], 'quantity')
            ->orderBy('name')
            ->get()
            ->map(function (VaccineType $vaccine): VaccineType {
                $vaccine->available_stock = (int) ($vaccine->stock_in ?? 0) - (int) ($vaccine->stock_out ?? 0);

                return $vaccine;
            });
        $transactions = VaccineInventoryTransaction::query()
            ->where('barangay_id', $barangayId)
            ->with(['vaccineType', 'inventoryItem', 'recorder'])
            ->latest('transaction_date')
            ->latest('created_at')
            ->get();

        return Pdf::view('vaccine-inventory.report', compact('barangay', 'items', 'transactions', 'vaccineBalances'))
            ->format('a4')
            ->landscape()
            ->margins(8, 8, 8, 8)
            ->name('vaccine-inventory-'.$barangay->id.'-'.now()->format('Ymd').'.pdf');
    }

    public function csv(Request $request)
    {
        $user = auth()->user();
        $this->authorizeInventory($user);
        $validated = $request->validate(['barangay' => ['nullable', 'exists:barangays,id']]);
        $barangayId = $user->isSuperAdmin() ? ($validated['barangay'] ?? null) : $user->barangay_id;

        if (! $barangayId) {
            return back()->withErrors(['barangay' => 'Select a barangay before exporting inventory data.']);
        }

        $barangay = Barangay::findOrFail($barangayId);
        $transactions = VaccineInventoryTransaction::query()
            ->where('barangay_id', $barangayId)
            ->with(['vaccineType', 'inventoryItem', 'recorder'])
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get();

        AuditLog::recordAction('exported', 'Exported vaccine inventory data', $barangay, ['format' => 'csv']);

        return CsvExport::download('vaccine-inventory-'.$barangay->id.'-'.now()->format('Ymd').'.csv', [
            'transaction_id', 'barangay', 'transaction_date', 'item_code', 'vaccine', 'vaccine_code',
            'batch_number', 'expiry_date', 'transaction_type', 'movement', 'quantity', 'signed_quantity',
            'reference_number', 'recorded_by', 'notes',
        ], $transactions->map(fn (VaccineInventoryTransaction $transaction): array => [
            $transaction->id,
            $barangay->name,
            $transaction->transaction_date?->toDateString(),
            $transaction->inventoryItem?->item_code ?? 'Legacy entry',
            $transaction->vaccineType?->name,
            $transaction->vaccineType?->code,
            $transaction->inventoryItem?->batch_number ?? $transaction->batch_number,
            ($transaction->inventoryItem?->expiry_date ?? $transaction->expiry_date)?->toDateString(),
            VaccineInventoryTransaction::TYPES[$transaction->transaction_type] ?? $transaction->transaction_type,
            $transaction->movement,
            $transaction->quantity,
            $transaction->signedQuantity(),
            $transaction->reference_number,
            $transaction->recorder?->name,
            $transaction->notes,
        ]));
    }

    public function create(): View
    {
        $user = auth()->user();
        $this->authorizeInventory($user);

        return view('vaccine-inventory.create', [
            'barangays' => $user->isSuperAdmin() ? Barangay::orderBy('name')->get() : collect(),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeInventory($user);

        if ($request->has('stocks')) {
            return $this->storeStockBatch($request, $user);
        }

        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,id'],
            'vaccine_type_id' => ['nullable', 'exists:vaccine_types,id'],
            'vaccine_inventory_item_id' => ['nullable', 'exists:vaccine_inventory_items,id'],
            'transaction_type' => ['required', 'string', 'in:'.implode(',', array_keys(VaccineInventoryTransaction::TYPES))],
            'movement' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $user->isSuperAdmin() && (string) $validated['barangay_id'] !== (string) $user->barangay_id) {
            abort(403);
        }

        $inventoryItem = null;
        if ($validated['transaction_type'] !== 'receipt' && filled($validated['vaccine_inventory_item_id'] ?? null)) {
            $inventoryItem = VaccineInventoryItem::query()
                ->whereKey($validated['vaccine_inventory_item_id'])
                ->where('barangay_id', $validated['barangay_id'])
                ->firstOrFail();
            $validated['vaccine_type_id'] = $inventoryItem->vaccine_type_id;
        }

        if ($validated['transaction_type'] !== 'receipt'
            && blank($validated['vaccine_inventory_item_id'] ?? null)
            && blank($validated['vaccine_type_id'] ?? null)) {
            return back()->withErrors(['vaccine_inventory_item_id' => 'Select an existing stock item.'])->withInput();
        }

        if ($validated['transaction_type'] === 'receipt' && blank($validated['vaccine_type_id'] ?? null)) {
            return back()->withErrors(['vaccine_type_id' => 'Select a vaccine for the new inventory item.'])->withInput();
        }

        if ($validated['transaction_type'] === 'receipt' && $validated['movement'] !== 'in') {
            return back()->withErrors(['movement' => 'A stock receipt must add stock.'])->withInput();
        }

        if (in_array($validated['transaction_type'], ['usage', 'expired', 'damaged'], true) && $validated['movement'] !== 'out') {
            return back()->withErrors(['movement' => 'This transaction type must remove stock.'])->withInput();
        }

        if ($validated['movement'] === 'out') {
            $available = $inventoryItem
                ? $this->availableItemStock($inventoryItem->id)
                : $this->availableStock($validated['barangay_id'], $validated['vaccine_type_id']);
            if ($validated['quantity'] > $available) {
                return back()->withErrors(['quantity' => "Only {$available} doses are currently available."])->withInput();
            }
        }

        DB::transaction(function () use (&$validated, $user): void {
            if ($validated['transaction_type'] === 'receipt') {
                $item = VaccineInventoryItem::create([
                    'item_code' => 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                    'barangay_id' => $validated['barangay_id'],
                    'vaccine_type_id' => $validated['vaccine_type_id'],
                    'batch_number' => $validated['batch_number'] ?? null,
                    'expiry_date' => $validated['expiry_date'] ?? null,
                    'received_at' => $validated['transaction_date'],
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
                $validated['vaccine_inventory_item_id'] = $item->id;
            }

            VaccineInventoryTransaction::create([
                ...$validated,
                'recorded_by' => $user->id,
            ]);
        });

        return to_route('vaccine-inventory.index', ['barangay' => $user->isSuperAdmin() ? $validated['barangay_id'] : null])
            ->with('status', 'Inventory transaction recorded.');
    }

    private function storeStockBatch(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,id'],
            'transaction_type' => ['required', 'in:receipt'],
            'movement' => ['required', 'in:in'],
            'stocks' => ['required', 'array', 'min:1', 'max:100'],
            'stocks.*.vaccine_type_id' => ['required', 'exists:vaccine_types,id'],
            'stocks.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'stocks.*.batch_number' => ['nullable', 'string', 'max:100'],
            'stocks.*.expiry_date' => ['nullable', 'date'],
            'stocks.*.transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'stocks.*.reference_number' => ['nullable', 'string', 'max:100'],
            'stocks.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $user->isSuperAdmin() && (string) $validated['barangay_id'] !== (string) $user->barangay_id) {
            abort(403);
        }

        DB::transaction(function () use ($validated, $user): void {
            foreach ($validated['stocks'] as $stock) {
                $item = VaccineInventoryItem::create([
                    'item_code' => 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                    'barangay_id' => $validated['barangay_id'],
                    'vaccine_type_id' => $stock['vaccine_type_id'],
                    'batch_number' => $stock['batch_number'] ?? null,
                    'expiry_date' => $stock['expiry_date'] ?? null,
                    'received_at' => $stock['transaction_date'],
                    'reference_number' => $stock['reference_number'] ?? null,
                    'notes' => $stock['notes'] ?? null,
                ]);

                VaccineInventoryTransaction::create([
                    'barangay_id' => $validated['barangay_id'],
                    'vaccine_type_id' => $stock['vaccine_type_id'],
                    'vaccine_inventory_item_id' => $item->id,
                    'recorded_by' => $user->id,
                    'transaction_type' => 'receipt',
                    'movement' => 'in',
                    'quantity' => $stock['quantity'],
                    'batch_number' => $stock['batch_number'] ?? null,
                    'expiry_date' => $stock['expiry_date'] ?? null,
                    'transaction_date' => $stock['transaction_date'],
                    'reference_number' => $stock['reference_number'] ?? null,
                    'notes' => $stock['notes'] ?? null,
                ]);
            }
        });

        return to_route('vaccine-inventory.index', ['barangay' => $user->isSuperAdmin() ? $validated['barangay_id'] : null])
            ->with('status', count($validated['stocks']).' stock item(s) saved.');
    }

    public function destroy(VaccineInventoryItem $inventoryItem): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeInventory($user);

        abort_if(! $user->isSuperAdmin() && (string) $inventoryItem->barangay_id !== (string) $user->barangay_id, 403);

        $hasFollowUpTransactions = $inventoryItem->transactions()
            ->where('transaction_type', '!=', 'receipt')
            ->exists();

        if ($hasFollowUpTransactions) {
            return back()->withErrors(['inventory' => 'This stock cannot be removed because it already has usage or adjustment history.']);
        }

        DB::transaction(function () use ($inventoryItem): void {
            $inventoryItem->transactions()->delete();
            $inventoryItem->delete();
        });

        return back()->with('status', 'Stock item removed.');
    }

    private function availableStock(string $barangayId, string $vaccineTypeId): int
    {
        return (int) VaccineInventoryTransaction::query()
            ->where('barangay_id', $barangayId)
            ->where('vaccine_type_id', $vaccineTypeId)
            ->selectRaw("coalesce(sum(case when movement = 'in' then quantity else -quantity end), 0) as balance")
            ->value('balance');
    }

    private function availableItemStock(string $itemId): int
    {
        return (int) VaccineInventoryTransaction::query()
            ->where('vaccine_inventory_item_id', $itemId)
            ->selectRaw("coalesce(sum(case when movement = 'in' then quantity else -quantity end), 0) as balance")
            ->value('balance');
    }

    private function inventoryItems(string $barangayId): Collection
    {
        if ($barangayId === '') {
            return collect();
        }

        return VaccineInventoryItem::query()
            ->forBarangay($barangayId)
            ->with('vaccineType')
            ->withSum(['transactions as stock_in' => fn ($query) => $query->where('movement', 'in')], 'quantity')
            ->withSum(['transactions as stock_out' => fn ($query) => $query->where('movement', 'out')], 'quantity')
            ->orderBy('expiry_date')
            ->orderBy('item_code')
            ->get()
            ->map(function (VaccineInventoryItem $item): VaccineInventoryItem {
                $item->available_stock = (int) ($item->stock_in ?? 0) - (int) ($item->stock_out ?? 0);

                return $item;
            })
            ->filter(fn (VaccineInventoryItem $item): bool => $item->available_stock > 0)
            ->values();
    }

    private function authorizeInventory(?User $user): void
    {
        abort_unless($user?->canManageInventory(), 403);
    }
}
