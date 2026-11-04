<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPermission
{
    public function handle(Request $request, Closure $next, string $module, ?string $component = null): Response
    {
        $user = $request->user();
        if (! $user || $user->status === 'inactive') {
            abort(403, 'You do not have permission to access this feature.');
        }

        if (($module === 'settings' && ! $user->isAdministrator())
            || ! $user->hasPermission($module, $component)) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->to($user->landingPath());
            }

            abort(403, 'You do not have permission to access this feature.');
        }

        return $next($request);
    }
}
