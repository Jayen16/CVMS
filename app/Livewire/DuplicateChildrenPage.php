<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Services\DuplicateChildMergeService;
use App\Services\DuplicateChildDetectionService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Duplicate Child Detection')]
class DuplicateChildrenPage extends Component
{
    public function mergeGroup(string $signature, int $keepChildId, DuplicateChildDetectionService $duplicates, DuplicateChildMergeService $merger): void
    {
        abort_unless(auth()->user()->canMergeDuplicates(), 403);

        $group = collect($duplicates->detect($this->visibleChildren()))
            ->firstWhere('signature', $signature);

        abort_if($group === null, 404);

        $target = collect($group['children'])->firstWhere('id', $keepChildId);

        abort_if($target === null, 404);

        if (! auth()->user()->isSuperAdmin()) {
            abort_if($target->barangay_id !== auth()->user()->barangay_id, 403);
            abort_if(collect($group['children'])->contains(fn (ChildProfile $child) => $child->barangay_id !== auth()->user()->barangay_id), 403);
        }

        $merger->mergeInto(
            $target,
            collect($group['children'])->reject(fn (ChildProfile $child) => $child->id === $target->id)
        );

        Flux::toast(variant: 'success', text: 'Duplicate child records merged into the selected profile.');
    }

    public function render(DuplicateChildDetectionService $duplicates): View
    {
        abort_unless(auth()->user()->canViewDuplicates(), 403);

        return view('livewire.duplicate-children-page', [
            'groups' => $duplicates->detect($this->visibleChildren()),
        ])->layout('layouts.app', ['title' => 'Duplicate Child Detection']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ChildProfile>
     */
    private function visibleChildren()
    {
        return ChildProfile::query()
            ->with('barangay')
            ->when(! auth()->user()->isSuperAdmin(), fn ($query) => $query->where('barangay_id', auth()->user()->barangay_id))
            ->orderBy('last_name')
            ->get();
    }
}
