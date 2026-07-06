<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->getPreferredLanguage(self::SUPPORTED)
            ?? config('app.locale');

        app()->setLocale(in_array($locale, self::SUPPORTED) ? $locale : config('app.locale'));

        return $next($request);
    }
}
