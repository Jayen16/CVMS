<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\ChildProfile;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ChildCreatePage extends Component
{
    public function render(): View
    {
        abort_unless(auth()->user()->canManageChildren(), 403);
        abort_if(auth()->user()->barangay_id === null, 403, 'A nurse must be assigned to a barangay before creating child profiles.');

        return view('children.create', [
            'child' => new ChildProfile(),
            'barangays' => Barangay::orderBy('name')->get(),
        ])->layout('layouts.app', [
            'title' => 'New child',
        ]);
    }
}
