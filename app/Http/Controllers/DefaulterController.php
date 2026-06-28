<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DefaulterController extends Controller
{
    public function index(Request $request, ImmunizationSuggestionService $suggestions): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $threshold = in_array($request->integer('days'), [7, 14, 30], true) ? $request->integer('days') : 7;
        $children = ChildProfile::query()
            ->with(['barangay', 'parents'])
            ->when(auth()->user()->isNurse(), fn ($query) => $query->where('barangay_id', auth()->user()->barangay_id))
            ->get();

        $today = Carbon::today();
        $defaulters = $children->map(function (ChildProfile $child) use ($suggestions, $today) {
            $suggestion = $suggestions->suggestNextDose($child);

            if ($suggestion['status'] !== 'overdue' || $suggestion['due_at'] === null) {
                return null;
            }

            return [
                'child' => $child,
                'suggestion' => $suggestion,
                'days_overdue' => (int) $suggestion['due_at']->diffInDays($today),
            ];
        })
            ->filter(fn ($item) => $item !== null && $item['days_overdue'] >= $threshold)
            ->sortByDesc('days_overdue')
            ->values();

        return view('queues.defaulters', [
            'defaulters' => $defaulters,
            'threshold' => $threshold,
        ]);
    }
}
