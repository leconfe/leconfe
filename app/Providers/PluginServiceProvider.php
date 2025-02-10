<?php

namespace App\Providers;

use App\Classes\DefaultTheme;
use App\Classes\ManualPaymentPlugin;
use App\Facades\Plugin;
use App\Managers\PluginManager;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->scoped('plugin', function (): PluginManager {
            return new PluginManager;
        });

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Plugin::register('DefaultTheme', new DefaultTheme, true);
        Plugin::register('ManualPayment', new ManualPaymentPlugin, true);
        Plugin::initialize();
    }
}
