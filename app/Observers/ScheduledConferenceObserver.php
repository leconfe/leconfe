<?php

namespace App\Observers;

use App\Actions\Committees\CommitteeRolePopulateDefaultDataAction;
use App\Actions\Roles\RolePopulateScheduledConferenceAction;
use App\Actions\ScheduledConferences\ScheduledConferenceRegisterEntityAction;
use App\Actions\Speakers\SpeakerRolePopulateDefaultDataAction;
use App\Actions\SubmissionFiles\FilesTypePopulateAction;
use App\Actions\Tracks\TrackPopulateAction;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\ScheduledConference;

class ScheduledConferenceObserver
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
    public function created(ScheduledConference $scheduledConference): void
    {
        CommitteeRolePopulateDefaultDataAction::run($scheduledConference);
        SpeakerRolePopulateDefaultDataAction::run($scheduledConference);

        $primaryNavigationMenu = NavigationMenu::create([
            'name' => 'Primary Navigation Menu',
            'handle' => 'primary-navigation-menu',
            'conference_id' => $scheduledConference->conference_id,
            'scheduled_conference_id' => $scheduledConference->getKey(),
        ]);

        $userNavigationMenu = NavigationMenu::create([
            'name' => 'User Navigation Menu',
            'handle' => 'user-navigation-menu',
            'conference_id' => $scheduledConference->conference_id,
            'scheduled_conference_id' => $scheduledConference->getKey(),
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
                'label' => 'About',
                'type' => 'about',
                'order_column' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'navigation_menu_id' => $primaryNavigationMenu->getKey(),
                'label' => 'Announcements',
                'type' => 'announcements',
                'order_column' => 3,
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

        TrackPopulateAction::run($scheduledConference);
        RolePopulateScheduledConferenceAction::run($scheduledConference);
        FilesTypePopulateAction::run($scheduledConference);
        ScheduledConferenceRegisterEntityAction::dispatch($scheduledConference);
    }
}
