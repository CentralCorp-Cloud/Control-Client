<?php

use App\Exceptions\NodeAgentException;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureControlPlaneAvailable;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['admin' => EnsureAdministrator::class, 'admin.2fa' => EnsureTwoFactorEnabled::class, 'control-plane.available' => EnsureControlPlaneAvailable::class]);
        $middleware->preventRequestForgery(except: ['stripe/webhook', '_system/webcron']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (NodeAgentException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->clientMessage(),
                    'correlation_id' => $exception->correlationId,
                ], $exception->httpStatus);
            }

            $response = redirect()->back()->with('error', $exception->clientMessage());
            if ($exception->retryAfter !== null) {
                $response->header('Retry-After', (string) $exception->retryAfter);
            }

            return $response;
        });
    })->create();
