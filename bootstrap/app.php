<?php

use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureStaffPermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'administrator' => EnsureAdministrator::class,
            'staff.permission' => EnsureStaffPermission::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Only needed behind a reverse proxy/load balancer. Leave unset when
        // the application receives traffic directly.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A stale session token should never leave the user stranded on the
         * bare "419 Page Expired" screen (this happens most often when a
         * session expires and the user then clicks "Sign out"). Clear whatever
         * session is left and send them back to the sign-in page instead.
         *
         * Laravel converts TokenMismatchException to a 419 HttpException before
         * render callbacks run, so we match the status code instead of the
         * original exception class. JSON requests keep a 419 so the client can
         * handle it explicitly.
         */
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            if ($request->hasSession()) {
                if (Auth::check()) {
                    Auth::logout();
                }

                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please sign in again.',
                    'session_expired' => true,
                ], 419);
            }

            return redirect()
                ->route('login')
                ->with('status', 'Your session expired. Please sign in again.');
        });
    })->create();
