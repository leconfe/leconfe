<?php

namespace App\Observers;

use App\Actions\Authors\AuthorRolePopulateDefaultDataAction;
use App\Actions\Roles\RolePopulateConferenceAction;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\Role;

class ConferenceObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the Conference "created" event.
     */
    public function created(Conference $conference): void
    {
        AuthorRolePopulateDefaultDataAction::run($conference);

        $primaryNavigationMenu = NavigationMenu::create([
            'name' => 'Primary Navigation Menu',
            'handle' => 'primary-navigation-menu',
            'conference_id' => $conference->getKey(),
        ]);

        $userNavigationMenu = NavigationMenu::create([
            'name' => 'User Navigation Menu',
            'handle' => 'user-navigation-menu',
            'conference_id' => $conference->getKey(),
        ]);

        NavigationMenuItem::insert([
            [
                'navigation_menu_id' => $primaryNavigationMenu->getKey(),
                'label' => 'Home',
                'type' => 'home',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'navigation_menu_id' => $primaryNavigationMenu->getKey(),
                'label' => 'Proceedings',
                'type' => 'proceedings',
                'order_column' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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

        RolePopulateConferenceAction::run($conference);

        $conferenceManager = Role::withoutGlobalScopes()->where('name', UserRole::ConferenceManager)->where('conference_id', $conference->getKey())->first();
        if ($conferenceManager && auth()->id()) {
            $conferenceManager->users()->attach(auth()->id(), ['conference_id' => $conference->getKey()]);
        }
    }

    /**
     * Handle the Conference "updated" event.
     */
    public function updated(Conference $conference): void
    {
        //
    }

    /**
     * Handle the Conference "deleted" event.
     */
    public function deleted(Conference $conference): void
    {
        //
    }

    /**
     * Handle the Conference "deleted" event.
     */
    public function deleting(Conference $conference): void {}

    /**
     * Handle the Conference "restored" event.
     */
    public function restored(Conference $conference): void
    {
        //
    }

    /**
     * Handle the Conference "force deleted" event.
     */
    public function forceDeleted(Conference $conference): void
    {
        //
    }
}
