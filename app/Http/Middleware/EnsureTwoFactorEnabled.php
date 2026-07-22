<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdministrator() && ! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('security.index')->with('warning', 'La double authentification est obligatoire pour les comptes administrateurs.');
        }

        return $next($request);
    }
}
