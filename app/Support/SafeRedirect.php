<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirects back to the previous page only when it belongs to this site.
 */
class SafeRedirect
{
    public static function back(Request $request, string $fallback): RedirectResponse
    {
        return redirect()->to(static::backUrl($request, $fallback));
    }

    public static function backUrl(Request $request, string $fallback): string
    {
        $referer = $request->headers->get('referer');

        return static::isSameHost($referer, $request) ? $referer : $fallback;
    }

    public static function isSameHost(?string $url, Request $request): bool
    {
        if (blank($url) || preg_match('/[\\\\\s\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host !== strtolower($request->getHost())) {
            return false;
        }

        if (isset($parts['port'])) {
            return (int) $parts['port'] === (int) $request->getPort();
        }

        return true;
    }
}
