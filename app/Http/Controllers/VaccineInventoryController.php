<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
            ->with(['barangay', 'vaccineType', 'recorder'])
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeInventory($user);

        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,id'],
            'vaccine_type_id' => ['required', 'exists:vaccine_types,id'],
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

        if ($validated['transaction_type'] === 'receipt' && $validated['movement'] !== 'in') {
            return back()->withErrors(['movement' => 'A stock receipt must add stock.'])->withInput();
        }

        if (in_array($validated['transaction_type'], ['usage', 'expired', 'damaged'], true) && $validated['movement'] !== 'out') {
            return back()->withErrors(['movement' => 'This transaction type must remove stock.'])->withInput();
        }

        if ($validated['movement'] === 'out') {
            $available = $this->availableStock($validated['barangay_id'], $validated['vaccine_type_id']);
            if ($validated['quantity'] > $available) {
                return back()->withErrors(['quantity' => "Only {$available} doses are currently available."])->withInput();
            }
        }

        DB::transaction(fn () => VaccineInventoryTransaction::create([
            ...$validated,
            'recorded_by' => $user->id,
        ]));

        return to_route('vaccine-inventory.index', ['barangay' => $user->isSuperAdmin() ? $validated['barangay_id'] : null])
            ->with('status', 'Inventory transaction recorded.');
    }

    private function availableStock(string $barangayId, string $vaccineTypeId): int
    {
        return (int) VaccineInventoryTransaction::query()
            ->where('barangay_id', $barangayId)
            ->where('vaccine_type_id', $vaccineTypeId)
            ->selectRaw("coalesce(sum(case when movement = 'in' then quantity else -quantity end), 0) as balance")
            ->value('balance');
    }

    private function authorizeInventory(?User $user): void
    {
        abort_unless($user?->canManageInventory(), 403);
    }
}
