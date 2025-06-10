<?php

namespace App\Observers;

use App\Application;
use App\Models\Enums\UserRole;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\Role;
use App\Models\Site;

class SiteObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the Site "created" event.
     */
    public function created(Site $site): void
    {
        $primaryNavigationMenu = NavigationMenu::create([
            'name' => 'Primary Navigation Menu',
            'handle' => 'primary-navigation-menu',
        ]);
        $userNavigationMenu = NavigationMenu::create([
            'name' => 'User Navigation Menu',
            'handle' => 'user-navigation-menu',
        ]);

        NavigationMenuItem::insert([
            [
                'navigation_menu_id' => $userNavigationMenu->getKey(),
                'label' => 'Login',
                'type' => 'login',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'navigation_menu_id' => $userNavigationMenu->getKey(),
                'label' => 'Register',
                'type' => 'register',
                'order_column' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $usernameNavigation = NavigationMenuItem::create([
            'navigation_menu_id' => $userNavigationMenu->getKey(),
            'label' => '{$username}',
            'type' => 'dashboard',
            'order_column' => 3,
        ]);

        NavigationMenuItem::insert([
            [
                'navigation_menu_id' => $userNavigationMenu->getKey(),
                'parent_id' => $usernameNavigation->getKey(),
                'label' => 'Dashboard',
                'type' => 'dashboard',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'navigation_menu_id' => $userNavigationMenu->getKey(),
                'parent_id' => $usernameNavigation->getKey(),
                'label' => 'Profile',
                'type' => 'profile',
                'order_column' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'navigation_menu_id' => $userNavigationMenu->getKey(),
                'parent_id' => $usernameNavigation->getKey(),
                'label' => 'Logout',
                'type' => 'logout',
                'order_column' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Role::firstOrCreate(['name' => UserRole::Admin]);
    }

    /**
     * Handle the Site "updated" event.
     */
    public function updated(Site $site): void
    {
        //
    }

    /**
     * Handle the Site "deleted" event.
     */
    public function deleted(Site $site): void
    {
        //
    }

    /**
     * Handle the Site "deleted" event.
     */
    public function deleting(Site $site): void {}

    /**
     * Handle the Site "restored" event.
     */
    public function restored(Site $site): void
    {
        //
    }

    /**
     * Handle the Site "force deleted" event.
     */
    public function forceDeleted(Site $site): void
    {
        //
    }
}
