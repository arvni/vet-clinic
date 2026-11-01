<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale = signed-in user's saved locale, else Accept-Language (fa/en), else fa.
 */
class SetLocale
{
    private const SUPPORTED = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $userLocale = $request->user('sanctum')?->locale;
        $locale = in_array($userLocale, self::SUPPORTED, true)
            ? $userLocale
            : ($request->getPreferredLanguage(self::SUPPORTED) ?? 'fa');

        // getPreferredLanguage falls back to the first supported value when nothing matches.
        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
