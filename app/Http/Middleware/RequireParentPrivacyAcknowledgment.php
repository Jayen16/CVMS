<?php

namespace App\Http\Middleware;

use App\Support\PrivacyNotice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireParentPrivacyAcknowledgment
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isParent() && $user->privacy_notice_version !== PrivacyNotice::VERSION) {
            return to_route('privacy.acknowledgment');
        }

        return $next($request);
    }
}
