<?php

namespace Tests\Feature;

use App\Frontend\Conference\Pages\Sitemap as ConferenceSitemap;
use App\Frontend\ScheduledConference\Pages\Home as ScheduledConferenceHome;
use App\Frontend\ScheduledConference\Pages\Register;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use App\Panel\ScheduledConference\Pages\Dashboard;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduledConferenceAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_admin_is_visible_in_conference_user_search_and_can_self_edit(): void
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
        $this->assertTrue(app(UserPolicy::class)->update($admin, $admin));
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

    public function test_participant_registration_page_requires_registration_to_be_allowed(): void
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
        $scheduledConference->setManyMeta([
            'allow_registration' => false,
            'participant_payment' => true,
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $participant = User::factory()->create([
            'email' => 'participant-access@example.test',
            'password' => Hash::make('password12345'),
        ]);
        $participant->assignRole($this->createScheduledConferenceRole(UserRole::Participant, $conference, $scheduledConference));

        $this->actingAs($participant);

        $this->assertFalse(ParticipantRegistration::canAccess());
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

    public function test_unpublished_scheduled_conference_public_page_shows_unpublished_information(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Draft Scheduled Conference',
            'path' => 'draft-scheduled-conference',
            'is_published' => false,
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $this->withoutVite()
            ->get(route(ScheduledConferenceHome::getRouteName('scheduledConference'), [
                'conference' => $conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->assertSee($scheduledConference->title)
            ->assertSee(__('scheduled_conference.unpublished_title'));
    }

    public function test_conference_sitemap_excludes_unpublished_scheduled_conferences(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Published Scheduled Conference',
            'path' => 'published-scheduled-conference',
            'is_published' => true,
        ]);

        ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Draft Scheduled Conference',
            'path' => 'draft-scheduled-conference',
            'is_published' => false,
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        Cache::forget('sitemap_'.$conference->getKey());

        $content = $this
            ->get(route(ConferenceSitemap::getRouteName('conference'), [
                'conference' => $conference->path,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/test-conference/scheduled/published-scheduled-conference', $content);
        $this->assertStringNotContainsString('/test-conference/scheduled/draft-scheduled-conference', $content);
    }

    public function test_previous_scheduled_author_is_not_listed_in_new_conference_or_schedule_context(): void
    {
        $previousConference = Conference::query()->create([
            'name' => 'Previous Conference',
            'path' => 'previous-conference',
        ]);
        $previousScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $previousConference->getKey(),
            'title' => 'Previous Scheduled Conference',
            'path' => 'previous-schedule',
        ]);

        $newConference = Conference::query()->create([
            'name' => 'New Conference',
            'path' => 'new-conference',
        ]);
        $newScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $newConference->getKey(),
            'title' => 'New Scheduled Conference',
            'path' => 'new-schedule',
        ]);

        $this->createAdminRole();

        $admin = User::factory()->create([
            'email' => 'admin-new-context@example.test',
            'password' => Hash::make('password12345'),
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $previousAuthor = User::factory()->create([
            'email' => 'previous-author@example.test',
            'password' => Hash::make('password12345'),
        ]);
        $currentAuthor = User::factory()->create([
            'email' => 'current-author@example.test',
            'password' => Hash::make('password12345'),
        ]);

        $previousAuthor->assignRole(Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Author->value,
            'guard_name' => 'web',
            'conference_id' => $previousConference->getKey(),
            'scheduled_conference_id' => $previousScheduledConference->getKey(),
        ]));
        $currentAuthor->assignRole(Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Author->value,
            'guard_name' => 'web',
            'conference_id' => $newConference->getKey(),
            'scheduled_conference_id' => $newScheduledConference->getKey(),
        ]));

        $this->actingAs($admin);

        app()->setCurrentConferenceId($newConference->getKey());
        app()->setCurrentScheduledConferenceId($newScheduledConference->getKey());

        $scheduledVisibleUserIds = UserResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($scheduledVisibleUserIds->contains($admin->getKey()));
        $this->assertTrue($scheduledVisibleUserIds->contains($currentAuthor->getKey()));
        $this->assertFalse($scheduledVisibleUserIds->contains($previousAuthor->getKey()));

        app()->setCurrentScheduledConferenceId(0);

        $conferenceVisibleUserIds = UserResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($conferenceVisibleUserIds->contains($admin->getKey()));
        $this->assertFalse($conferenceVisibleUserIds->contains($previousAuthor->getKey()));
        $this->assertFalse($conferenceVisibleUserIds->contains($currentAuthor->getKey()));
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

    protected function createScheduledConferenceRole(
        UserRole $role,
        Conference $conference,
        ScheduledConference $scheduledConference
    ): Role {
        return Role::withoutGlobalScopes()->firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
        ]);
    }
}
