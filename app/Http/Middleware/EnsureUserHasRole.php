<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access control. Usage in routes: ->middleware('role:admin,intern')
 * Stack after 'auth' — this assumes a user is already resolved on the request.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            abort(403, 'Wala kang access sa page na ito.');
        }

        return $next($request);
    }
}
