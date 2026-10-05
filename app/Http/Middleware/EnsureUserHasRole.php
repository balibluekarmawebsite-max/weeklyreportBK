<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to users holding at least one of the given roles.
 * Usage: ->middleware('role:admin') or ->middleware('role:admin,editor').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! collect($roles)->contains(fn ($role) => $user->hasRole($role))) {
            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
