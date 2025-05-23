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
        
        $sessionLocale = session('locale') ?? Setting::get('default_language', app()->getLocale());
        
        $supportedLocales = Setting::get('languages', [app()->getLocale()]);
        
        $locale = $sessionLocale && in_array($sessionLocale, $supportedLocales) ? $sessionLocale : Setting::get('default_language', 'en');

        App::setLocale($locale);
        return $next($request);
    }
}
