<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['ka', 'en']);

        $locale = session('locale')
            ?? $request->user()?->locale
            ?? config('app.locale');

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);
        // Keep Carbon's date helpers in sync (used for dashboard "Tue, May 12" etc).
        \Carbon\Carbon::setLocale($locale);

        return $next($request);
    }
}
