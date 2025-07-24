<?php

namespace App\Providers;

use App\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelIgnition\Facades\Flare;

class FlareServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        Flare::determineVersionUsing(function () {
            return Application::getCodeVersion();
        });
        Flare::group('Informations', [
            'url' => url(),
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (App::isReportingErrors()) {
            Config::set('logging.channels.stack.channels', array_merge(config('logging.channels.stack.channels'), ['flare']));
        }
    }
}
