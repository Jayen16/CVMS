<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Services\DuplicateChildDetectionService;
use Illuminate\View\View;

class DuplicateChildController extends Controller
{
    public function index(DuplicateChildDetectionService $duplicates): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $children = ChildProfile::query()
            ->with('barangay')
            ->when(auth()->user()->isNurse(), fn ($query) => $query->where('barangay_id', auth()->user()->barangay_id))
            ->orderBy('last_name')
            ->get();

        return view('children.duplicates', [
            'groups' => $duplicates->detect($children),
        ]);
    }
}
