<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Services\DuplicateChildDetectionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Duplicate Child Detection')]
class DuplicateChildrenPage extends Component
{
    public function render(DuplicateChildDetectionService $duplicates): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $children = ChildProfile::query()
            ->with('barangay')
            ->when(auth()->user()->isNurse(), fn ($query) => $query->where('barangay_id', auth()->user()->barangay_id))
            ->orderBy('last_name')
            ->get();

        return view('livewire.duplicate-children-page', [
            'groups' => $duplicates->detect($children),
        ])->layout('layouts.app', ['title' => 'Duplicate Child Detection']);
    }
}
