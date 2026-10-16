<?php

namespace App\Http\Middleware;

use App\Support\Multilingual\ContentLocales;
use Closure;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        if (! app()->isInstalled()) {
            return $next($request);
        }

        $locales = app(ContentLocales::class);
        $sessionLocale = session('locale');

        $locale = $locales->isEnabled($sessionLocale) ? $sessionLocale : $locales->default();

        App::setLocale($locale);

        return $next($request);
    }
}
