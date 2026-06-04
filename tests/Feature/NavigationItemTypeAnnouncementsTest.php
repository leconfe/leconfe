<?php

namespace Tests\Feature;

use App\Models\NavigationItemType\Announcements;
use App\Models\NavigationMenuItem;
use Tests\TestCase;

class NavigationItemTypeAnnouncementsTest extends TestCase
{
    public function test_announcements_navigation_is_hidden_outside_scheduled_conference_context(): void
    {
        $this->assertFalse(Announcements::getIsDisplayed(new NavigationMenuItem()));
    }

    public function test_announcements_navigation_is_displayed_in_scheduled_conference_context(): void
    {
        app()->setCurrentScheduledConferenceId(123);

        $this->assertTrue(Announcements::getIsDisplayed(new NavigationMenuItem()));
    }
}
