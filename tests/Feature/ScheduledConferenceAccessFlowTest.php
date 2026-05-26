<?php

namespace Tests\Feature;

use App\Frontend\ScheduledConference\Pages\Register;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use App\Panel\ScheduledConference\Pages\Dashboard;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduledConferenceAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_admin_is_visible_in_conference_user_search_without_reopening_self_edit(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $this->createAdminRole();

        $admin = User::factory()->create([
            'email' => 'admin@leconfe.test',
            'password' => Hash::make('password12345'),
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $otherAdmin = User::factory()->create([
            'email' => 'other-admin@leconfe.test',
            'password' => Hash::make('password12345'),
        ]);
        $otherAdmin->assignRole(UserRole::Admin->value);

        app()->setCurrentConferenceId($conference->getKey());
        $this->actingAs($admin);

        $visibleUserIds = UserResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visibleUserIds->contains($admin->getKey()));
        $this->assertFalse($visibleUserIds->contains($otherAdmin->getKey()));
        $this->assertFalse(app(UserPolicy::class)->update($admin, $admin));
        $this->assertFalse(app(UserPolicy::class)->delete($admin, $admin));
    }

    public function test_participant_self_assign_role_only_appears_when_participant_payment_is_enabled(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'test',
        ]);
        $scheduledConference->setManyMeta(['participant_payment' => false]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $this->assertNotContains(UserRole::Participant->name, UserRole::getAllowedSelfAssignRoleNames());

        $enabledScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Enabled Scheduled Conference',
            'path' => 'enabled',
        ]);
        $enabledScheduledConference->setManyMeta(['participant_payment' => true]);

        app()->setCurrentScheduledConferenceId($enabledScheduledConference->getKey());

        $this->assertContains(UserRole::Participant->name, UserRole::getAllowedSelfAssignRoleNames());
    }

    public function test_scheduled_conference_registration_redirect_url_points_to_panel_dashboard(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'scheduled',
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $this->assertSame(
            route(Dashboard::getRouteName('scheduledConference')),
            (new Register)->getRedirectUrl()
        );
    }

    protected function createAdminRole(): void
    {
        Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Admin->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]);
    }
}
