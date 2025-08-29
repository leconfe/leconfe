<?php

namespace App\Utils\UpgradeSchemas;

use App\Application;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use Illuminate\Support\Facades\Artisan;

class Upgrade130Beta4 extends UpgradeBase
{
    public function run(): void
    {
        $this->migrate();
        $this->addNavigation();
    }

    protected function migrate(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }

    protected function addNavigation(): void
    {
        $primaryNavigationMenu = NavigationMenu::query()
            ->where('handle', 'primary-navigation-menu')
            ->where('conference_id', Application::CONTEXT_WEBSITE)->first();

        NavigationMenuItem::firstOrCreate(
            [
                'navigation_menu_id' => $primaryNavigationMenu->getKey(),
                'type' => 'proceedings',
            ],
            [
                'label' => 'Proceedings',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
