<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account settings live in the dashboard, which only admins and sellers use.
 * A buyer is sent to their own profile page rather than shown a 403, since
 * that is where they edit the same details.
 */
class EnsureDashboardUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::denies('dashboard')) {
            return $request->expectsJson()
                ? abort(403, 'This area is for stores and admins.')
                : redirect()->route('profile.show');
        }

        return $next($request);
    }
}
