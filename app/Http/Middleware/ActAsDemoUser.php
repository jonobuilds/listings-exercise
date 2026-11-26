<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Take-home auth stub.
 *
 * A real deployment would authenticate the request (Sanctum, session, etc.).
 * To keep this exercise focused on the feature rather than on auth plumbing,
 * every web request is resolved as the seeded demo user. Build user-scoped
 * work against $request->user() / auth()->user() exactly as you normally would.
 */
class ActAsDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // The user is set on the guard, not just the request: auth()->user(),
        // auth()->id(), the Gate and policies all read from the guard, and the
        // request's own resolver delegates to it.
        //
        // A user that's already set (e.g. actingAs() in a test) is left alone.
        //
        // This leaves the request a guest until the database is seeded
        // (`php artisan migrate --seed`), the same shape as a genuinely
        // unauthenticated request.
        if (! Auth::hasUser()) {
            $demoUser = User::query()->orderBy('id')->first();

            if ($demoUser !== null) {
                Auth::setUser($demoUser);
            }
        }

        return $next($request);
    }
}
