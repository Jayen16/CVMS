<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\ChildProfile;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ChildEditPage extends Component
{
    public ChildProfile $child;

    public function mount(ChildProfile $child): void
    {
        $this->child = $child;
    }

    public function render(): View
    {
        $this->authorizeChildUpdate($this->child);

        return view('children.edit', [
            'child' => $this->child,
            'barangays' => Barangay::orderBy('name')->get(),
        ])->layout('layouts.app', [
            'title' => 'Edit child',
        ]);
    }

    private function authorizeChildUpdate(ChildProfile $child): void
    {
        abort_unless(auth()->user()->canManageChildren(), 403);
        abort_if(auth()->user()->barangay_id === null, 403);
        abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);
    }
}
