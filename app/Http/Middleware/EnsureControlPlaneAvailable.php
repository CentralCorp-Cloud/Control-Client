<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureControlPlaneAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::boolean('global_maintenance') && ! $request->user()?->isAdministrator()) {
            abort(503, 'CentralCloud est temporairement en maintenance.');
        }

        return $next($request);
    }
}
