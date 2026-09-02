<?php

namespace App\Http\Middleware;

use App\Services\FacilityActivationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireUnactivatedInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(FacilityActivationService::class)->localInstallation()->status !== 'active', 404);

        return $next($request);
    }
}
