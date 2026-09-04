<?php

namespace App\Http\Controllers;

use App\Support\PrivacyNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivacyAcknowledgmentController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->isParent(), 404);

        if ($request->user()->privacy_notice_version === PrivacyNotice::VERSION) {
            return to_route('dashboard');
        }

        return view('pages.privacy-acknowledgment', ['version' => PrivacyNotice::VERSION]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isParent(), 404);

        $request->validate([
            'acknowledged' => ['accepted'],
        ]);

        $request->user()->forceFill([
            'privacy_notice_version' => PrivacyNotice::VERSION,
            'privacy_acknowledged_at' => now(),
            'privacy_acknowledged_ip' => $request->ip(),
        ])->save();

        return to_route('dashboard');
    }
}
