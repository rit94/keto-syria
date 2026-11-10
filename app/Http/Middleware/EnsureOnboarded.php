<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias: `onboarded`. Sends authenticated users who have no profile yet to the
 * onboarding page (once that route exists). Use after `auth` on feature routes.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && Route::has('onboarding')
            && ! $request->routeIs('onboarding', 'onboarding.*')
            && ! $user->profile()->exists()
        ) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
