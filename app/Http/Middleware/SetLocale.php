<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the UI locale: authenticated user's users.locale → session → config default (ar).
 * Appended to the `web` group, so it also runs for Livewire update requests.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('app.available_locales', ['ar', 'en']);

        $candidates = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            config('app.locale'),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, $available, true)) {
                app()->setLocale($locale);
                break;
            }
        }

        return $next($request);
    }
}
