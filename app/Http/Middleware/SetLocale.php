<?php

namespace App\Http\Middleware;

use App\Facades\Setting;
use Closure;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        if (! app()->isInstalled()) {
            return $next($request);
        }

        $sessionLocale = session('locale');
        $enabledLocales = array_values(array_intersect(
            Setting::get('languages', ['en']),
            array_keys(config('app.locales'))
        ));
        $defaultLocale = Setting::get('default_language', 'en');

        if (! in_array($defaultLocale, $enabledLocales, true)) {
            $defaultLocale = $enabledLocales[0] ?? 'en';
        }

        $locale = in_array($sessionLocale, $enabledLocales, true) ? $sessionLocale : $defaultLocale;

        App::setLocale($locale);

        return $next($request);
    }
}
