<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationQueueController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->canViewVerificationQueue(), 403);

        $query = VaccinationRecord::query()
            ->with(['child.barangay', 'vaccineType', 'submitter'])
            ->where('verification_status', 'pending');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereHas('child', fn ($builder) => $builder->where('barangay_id', auth()->user()->barangay_id));
        }

        $barangayId = $request->string('barangay_id')->toString() ?: null;
        $vaccineTypeId = $request->string('vaccine_type_id')->toString() ?: null;
        $source = $request->string('source')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        $query
            ->when($barangayId, fn ($builder) => $builder->whereHas('child', fn ($child) => $child->where('barangay_id', $barangayId)))
            ->when($vaccineTypeId, fn ($builder) => $builder->where('vaccine_type_id', $vaccineTypeId))
            ->when($source !== '', fn ($builder) => $builder->where('source', $source))
            ->when($from, fn ($builder) => $builder->whereDate('administered_at', '>=', $from))
            ->when($to, fn ($builder) => $builder->whereDate('administered_at', '<=', $to));

        return view('queues.verification', [
            'records' => $query->latest('administered_at')->paginate(15)->withQueryString(),
            'barangays' => Barangay::orderBy('name')->get(),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'filters' => compact('barangayId', 'vaccineTypeId', 'source'),
        ]);
    }
}
