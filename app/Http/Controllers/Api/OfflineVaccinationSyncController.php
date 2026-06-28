<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Services\ImmunizationSuggestionService;
use App\Services\VaccinationSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineVaccinationSyncController extends Controller
{
    public function store(
        Request $request,
        ChildProfile $child,
        VaccinationSubmissionService $submissions,
        ImmunizationSuggestionService $suggestions
    ): JsonResponse {
        $this->authorizeCreate($child);

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:100'],
        ]);

        $saved = $submissions->syncOfflineBatch(auth()->user(), $child, $validated['records']);

        foreach ($saved as $record) {
            if ($record instanceof VaccinationRecord) {
                $record->update($suggestions->suggestionForRecord($child));
            }
        }

        return response()->json([
            'message' => 'Offline vaccination records synced.',
            'saved' => collect($saved)->pluck('id')->all(),
        ]);
    }

    private function authorizeCreate(ChildProfile $child): void
    {
        if (auth()->user()->isNurse()) {
            abort_if($child->barangay_id !== auth()->user()->barangay_id, 403);

            return;
        }

        if (auth()->user()->isParent()) {
            abort_unless($child->parents()->whereKey(auth()->id())->exists(), 403);

            return;
        }

        abort(403);
    }
}
