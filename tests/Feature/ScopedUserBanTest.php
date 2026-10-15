<?php

namespace Tests\Feature;

use App\Actions\Announcements\AnnouncementBroadcastMail;
use App\Frontend\ScheduledConference\Pages\Login as ScheduledLogin;
use App\Frontend\Website\Pages\Login;
use App\Http\Middleware\InstallationMiddleware;
use App\Mail\Templates\NewAnnouncementMail;
use App\Models\Announcement;
use App\Models\Ban;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource\Pages\ListUsers;
use App\Support\UserBanScope;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class ScopedUserBanTest extends TestCase
{
    use RefreshDatabase;

    private Conference $conference;

    private ScheduledConference $event;

    private ScheduledConference $otherEvent;

    private Conference $otherConference;

    private ScheduledConference $foreignEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = Conference::factory()->create(['path' => 'first-conference']);
        $this->event = ScheduledConference::factory()->create([
            'conference_id' => $this->conference->id,
            'path' => 'first-event',
        ]);
        $this->otherEvent = ScheduledConference::factory()->create([
            'conference_id' => $this->conference->id,
            'path' => 'second-event',
        ]);
        $this->otherConference = Conference::factory()->create(['path' => 'second-conference']);
        $this->foreignEvent = ScheduledConference::factory()->create([
            'conference_id' => $this->otherConference->id,
            'path' => 'foreign-event',
        ]);
        $this->context($this->event);
    }

    public function test_scheduled_ban_only_affects_its_event_and_does_not_become_a_global_ban(): void
    {
        $user = $this->member();
        $this->actingAs($this->staff(UserRole::ScheduledConferenceEditor));

        $ban = $user->banInCurrentContext(['comment' => 'Repeated spam']);

        $this->assertSame($this->conference->id, $ban->conference_id);
        $this->assertSame($this->event->id, $ban->scheduled_conference_id);
        $this->assertSame(auth()->id(), $ban->created_by_id);
        $this->assertTrue($user->isBannedInCurrentContext());
        $this->assertFalse($user->isBanned());

        $this->context($this->otherEvent);
        $this->assertFalse($user->isBannedInCurrentContext());
        $this->context($this->foreignEvent);
        $this->assertFalse($user->isBannedInCurrentContext());
        $this->context($this->conference);
        $this->assertFalse($user->isBannedInCurrentContext());
        $this->context();
        $this->assertFalse($user->isBannedInCurrentContext());
    }

    public function test_conference_ban_applies_to_all_its_events_but_not_other_conferences(): void
    {
        $user = $this->member();
        $manager = $this->staff(UserRole::ConferenceManager);
        $this->context($this->conference);
        $this->actingAs($manager);

        $user->banInCurrentContext();

        $this->assertTrue($user->isBannedInCurrentContext());
        foreach ([$this->event, $this->otherEvent] as $event) {
            $this->context($event);
            $this->assertTrue($user->isBannedInCurrentContext());
        }
        $this->context($this->foreignEvent);
        $this->assertFalse($user->isBannedInCurrentContext());
        $this->context();
        $this->assertFalse($user->isBanned());
    }

    public function test_admin_can_create_global_ban_and_other_staff_cannot(): void
    {
        $user = $this->member();
        $manager = $this->staff(UserRole::ConferenceManager);
        $editor = $this->staff(UserRole::ScheduledConferenceEditor);
        $admin = $this->staff(UserRole::Admin);
        $this->context();

        $this->assertFalse(Gate::forUser($manager)->allows('disable', $user));
        $this->assertFalse(Gate::forUser($editor)->allows('disable', $user));
        $this->actingAs($admin);
        $ban = $user->banInCurrentContext();

        $this->assertNull($ban->conference_id);
        $this->assertNull($ban->scheduled_conference_id);
        $this->assertTrue($user->isBanned());
        $this->context($this->foreignEvent);
        $this->assertTrue($user->isBannedInCurrentContext());
    }

    public function test_scope_cannot_be_overridden_by_submitted_attributes(): void
    {
        $user = $this->member();
        $this->actingAs($this->staff(UserRole::ScheduledConferenceEditor));

        $ban = $user->banInCurrentContext([
            'conference_id' => $this->otherConference->id,
            'scheduled_conference_id' => null,
            'created_by_id' => 999,
            'ip' => '127.0.0.1',
            'comment' => 'Repeated spam',
        ]);

        $this->assertSame($this->conference->id, $ban->conference_id);
        $this->assertSame($this->event->id, $ban->scheduled_conference_id);
        $this->assertSame(auth()->id(), $ban->created_by_id);
        $this->assertNull($ban->ip);
    }

    public function test_editor_cannot_manage_conference_bans_or_other_events_even_with_preloaded_roles(): void
    {
        $user = $this->member();
        $editor = $this->staff(UserRole::ScheduledConferenceEditor);
        $editor->load('roles');
        $this->assertTrue(Gate::forUser($editor)->allows('disable', $user));

        $this->context($this->conference);
        $this->assertFalse(Gate::forUser($editor)->allows('disable', $user));
        $this->context($this->otherEvent);
        $this->assertFalse(Gate::forUser($editor)->allows('disable', $user));
        $this->context($this->foreignEvent);
        $this->assertFalse(Gate::forUser($editor)->allows('disable', $user));
    }

    public function test_manager_can_manage_event_bans_only_within_their_conference(): void
    {
        $manager = $this->staff(UserRole::ConferenceManager);
        $user = $this->member();
        $this->assertTrue(Gate::forUser($manager)->allows('disable', $user));

        $this->context($this->otherEvent);
        $otherUser = $this->member();
        $this->assertTrue(Gate::forUser($manager)->allows('disable', $otherUser));

        $this->context($this->foreignEvent);
        $foreignUser = $this->member();
        $this->assertFalse(Gate::forUser($manager)->allows('disable', $foreignUser));
    }

    public function test_staff_cannot_ban_a_user_who_is_not_a_member_of_the_current_scope(): void
    {
        $editor = $this->staff(UserRole::ScheduledConferenceEditor);
        $this->context($this->otherEvent);
        $foreignUser = $this->member();
        $this->context($this->event);
        $this->actingAs($editor);

        $this->expectException(AuthorizationException::class);
        $foreignUser->banInCurrentContext();
    }

    public function test_self_and_admin_bans_are_rejected_including_for_admin_actor(): void
    {
        $admin = $this->staff(UserRole::Admin);
        $otherAdmin = $this->staff(UserRole::Admin);
        $manager = $this->staff(UserRole::ConferenceManager);

        $this->assertFalse(Gate::forUser($admin)->allows('disable', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('disable', $otherAdmin));
        $this->assertFalse(Gate::forUser($manager)->allows('disable', $manager));
        $this->assertFalse(Gate::forUser($manager)->allows('disable', $admin));
    }

    public function test_default_non_management_roles_cannot_ban_users(): void
    {
        $user = $this->member();

        foreach ([UserRole::TrackEditor, UserRole::Reviewer, UserRole::Author, UserRole::Participant] as $role) {
            $this->assertFalse(Gate::forUser($this->staff($role))->allows('disable', $user));
        }
    }

    public function test_custom_management_role_inherits_its_permission_level_only_in_its_scope(): void
    {
        $user = $this->member();
        $editor = $this->staff(UserRole::ScheduledConferenceEditor);
        $role = $editor->roles()->first();
        $role->update(['name' => 'Event Moderator']);
        $role->setMeta('permission_level', UserRole::ScheduledConferenceEditor->value);

        $this->assertTrue(Gate::forUser($editor)->allows('disable', $user));
        $this->context($this->conference);
        $this->assertFalse(Gate::forUser($editor)->allows('disable', $user));
    }

    public function test_malformed_cross_conference_role_pivot_does_not_grant_ban_permission(): void
    {
        $user = $this->member();
        $editor = $this->staff(UserRole::ScheduledConferenceEditor);
        DB::table('model_has_roles')->where('model_id', $editor->id)
            ->update(['conference_id' => $this->otherConference->id]);

        $this->assertFalse(Gate::forUser($editor)->allows('disable', $user));
    }

    public function test_invalid_conference_event_pair_is_rejected_even_for_admin(): void
    {
        $admin = $this->staff(UserRole::Admin);
        $user = $this->member();
        app()->setCurrentConferenceId($this->otherConference->id);

        $this->assertFalse(Gate::forUser($admin)->allows('disable', $user));
    }

    public function test_unban_removes_only_the_current_scope_and_keeps_parent_and_other_event_bans(): void
    {
        $user = $this->member();
        $editor = $this->staff(UserRole::ScheduledConferenceEditor);
        $global = $user->ban();
        $conference = $this->seedBan($user, new UserBanScope($this->conference->id));
        $local = $this->seedBan($user, UserBanScope::current());
        $other = $this->seedBan($user, new UserBanScope($this->conference->id, $this->otherEvent->id));
        $this->actingAs($editor);

        $user->unbanInCurrentContext();

        $this->assertSoftDeleted('bans', ['id' => $local->id]);
        foreach ([$global, $conference, $other] as $ban) {
            $this->assertDatabaseHas('bans', ['id' => $ban->id, 'deleted_at' => null]);
        }
        $this->assertTrue($user->isBannedInCurrentContext());
        $this->assertFalse(Gate::allows('enable', $user));
    }

    public function test_editor_cannot_unban_inherited_conference_or_global_bans(): void
    {
        $user = $this->member();
        $this->seedBan($user, new UserBanScope($this->conference->id));
        $user->ban();
        $this->actingAs($this->staff(UserRole::ScheduledConferenceEditor));

        $this->expectException(AuthorizationException::class);
        $user->unbanInCurrentContext();
    }

    public function test_expired_bans_do_not_block_access_queries_or_new_bans(): void
    {
        $user = $this->member();
        $user->ban(['expired_at' => now()->subSecond()]);
        $this->seedBan($user, UserBanScope::current(), ['expired_at' => now()->subSecond()]);

        $this->assertFalse($user->isBannedInCurrentContext());
        $this->assertTrue(User::notBanned()->whereKey($user->id)->exists());
        $this->assertTrue(User::notBannedInScope(UserBanScope::current())->whereKey($user->id)->exists());
        $this->actingAs($this->staff(UserRole::ScheduledConferenceEditor));
        $user->banInCurrentContext(['expired_at' => now()->addDay()]);
        $this->assertTrue($user->isBannedInCurrentContext());
    }

    public function test_permanent_bans_do_not_leak_across_users_in_database_queries(): void
    {
        $banned = $this->member();
        $allowed = $this->member();
        $this->seedBan($banned, UserBanScope::current());

        $ids = User::notBannedInScope(UserBanScope::current())->pluck('id');
        $this->assertFalse($ids->contains($banned->id));
        $this->assertTrue($ids->contains($allowed->id));
        $this->assertTrue(User::notBanned()->whereKey($banned->id)->exists());
        $this->assertFalse(User::banned()->whereKey($banned->id)->exists());
    }

    public function test_legacy_rows_remain_global_when_scope_migration_is_applied(): void
    {
        $migration = require database_path('migrations/scopes_field_to_bans_table.php');
        $migration->down();
        $user = $this->member();
        $banId = DB::table('bans')->insertGetId([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Account disabled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration->up();

        $this->assertDatabaseHas('bans', [
            'id' => $banId, 'conference_id' => null, 'scheduled_conference_id' => null,
        ]);
        $this->assertTrue($user->isBanned());
        $this->assertTrue($user->isBannedInCurrentContext());
    }

    public function test_scoped_ban_blocks_web_and_post_requests_without_logging_out(): void
    {
        $user = $this->member();
        $this->seedBan($user, UserBanScope::current());
        $this->actingAs($user);
        $this->withoutMiddleware(InstallationMiddleware::class);
        Route::match(['GET', 'POST'], '/_tests/user-ban/access/check', fn () => response('allowed'))->middleware('web');

        $this->withSession(['unrelated_state' => 'preserved'])
            ->get('/_tests/user-ban/access/check')->assertForbidden()->assertSessionHas('unrelated_state', 'preserved');
        $this->assertAuthenticatedAs($user);
        $this->post('/_tests/user-ban/access/check')->assertForbidden();
        $this->assertAuthenticatedAs($user);

        $this->context($this->otherEvent);
        $this->get('/_tests/user-ban/access/check')->assertOk()->assertSee('allowed');
        $this->context();
        $this->get('/_tests/user-ban/access/check')->assertOk();
    }

    public function test_migration_rollback_revokes_scoped_bans_without_turning_them_into_global_bans(): void
    {
        $user = $this->member();
        $global = $user->ban();
        $local = $this->seedBan($user, UserBanScope::current());
        $conference = $this->seedBan($user, new UserBanScope($this->conference->id));
        $migration = require database_path('migrations/scopes_field_to_bans_table.php');

        $migration->down();

        $this->assertDatabaseHas('bans', ['id' => $global->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('bans', ['id' => $local->id]);
        $this->assertSoftDeleted('bans', ['id' => $conference->id]);
        $migration->up();
        $this->assertSame([$global->id], $user->bans()->pluck('id')->all());
    }

    public function test_global_ban_logs_out_and_invalidates_session_on_web_requests(): void
    {
        $user = $this->member();
        $user->ban();
        $this->actingAs($user);
        $this->withoutMiddleware(InstallationMiddleware::class);
        Route::get('/_tests/user-ban/access/check', fn () => response('allowed'))->middleware('web');

        $this->withSession(['unrelated_state' => 'removed'])
            ->get('/_tests/user-ban/access/check')->assertForbidden()->assertSessionMissing('unrelated_state');
        $this->assertGuest();
    }

    public function test_scoped_ban_does_not_prevent_explicit_logout(): void
    {
        $user = $this->member();
        $this->seedBan($user, UserBanScope::current());
        $this->actingAs($user);
        $this->withoutMiddleware(InstallationMiddleware::class);

        $this->get(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_scoped_banned_user_can_log_in_on_website_but_not_the_banned_event(): void
    {
        $user = $this->member();
        $this->seedBan($user, UserBanScope::current());
        $this->context();
        $this->withoutVite();
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect();
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->context($this->event);
        Livewire::test(ScheduledLogin::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')->assertForbidden();
    }

    public function test_global_ban_prevents_website_login(): void
    {
        $user = $this->member();
        $user->ban();
        $this->context();
        $this->withoutVite();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')->assertForbidden();
        $this->assertGuest();
    }

    public function test_user_table_actions_create_and_remove_only_contextual_bans(): void
    {
        $manager = $this->staff(UserRole::ConferenceManager);
        $user = $this->member();
        $this->context($this->conference);
        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('conference'));
        $this->preparePanelPermissions();
        $this->assertTrue($manager->can('User:viewAny'));
        $this->withoutVite();

        Livewire::test(ListUsers::class)
            ->assertStatus(200)
            ->assertCanSeeTableRecords([$user])
            ->callTableAction('disable', $user, ['comment' => 'Repeated spam', 'expired_at' => null])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('bans', [
            'bannable_id' => $user->id,
            'conference_id' => $this->conference->id,
            'scheduled_conference_id' => null,
            'comment' => 'Repeated spam',
        ]);
        $ban = $user->fresh()->activeBanInCurrentContext();
        Livewire::test(ListUsers::class)
            ->assertSee(__('general.conference'))
            ->callTableAction('enable', $user)
            ->assertHasNoTableActionErrors();
        $this->assertSoftDeleted('bans', ['id' => $ban->id]);
    }

    public function test_table_hides_unban_when_the_active_ban_is_inherited(): void
    {
        $user = $this->member();
        $user->ban();
        $this->actingAs($this->staff(UserRole::ScheduledConferenceEditor));
        Filament::setCurrentPanel(Filament::getPanel('scheduledConference'));
        $this->preparePanelPermissions();
        $this->withoutVite();

        Livewire::test(ListUsers::class)
            ->assertStatus(200)
            ->assertTableActionHidden('enable', $user)
            ->assertTableActionHidden('disable', $user)
            ->assertSee(__('ban.global'));
    }

    public function test_banned_users_cannot_be_impersonated_only_in_the_affected_context(): void
    {
        $user = $this->member();
        $this->seedBan($user, UserBanScope::current());
        $this->assertFalse($user->canBeImpersonated());
        $this->context($this->otherEvent);
        $this->assertTrue($user->canBeImpersonated());
    }

    public function test_announcement_recipients_follow_explicit_event_scope_in_background_jobs(): void
    {
        Mail::fake();
        $global = $this->member();
        $conference = $this->member();
        $event = $this->member();
        $otherEvent = $this->member();
        $expired = $this->member();
        foreach ([$global, $conference, $event, $otherEvent, $expired] as $user) {
            $user->setMeta('enable_new_announcement_email', true);
        }
        $global->ban();
        $this->seedBan($conference, new UserBanScope($this->conference->id));
        $this->seedBan($event, UserBanScope::current());
        $this->seedBan($otherEvent, new UserBanScope($this->conference->id, $this->otherEvent->id));
        $expired->ban(['expired_at' => now()->subDay()]);
        $announcement = Announcement::withoutGlobalScopes()->forceCreate([
            'scheduled_conference_id' => $this->event->id,
            'title' => 'Event update',
        ]);
        $this->context();

        app(AnnouncementBroadcastMail::class)->handle($announcement);

        Mail::assertQueued(NewAnnouncementMail::class, 2);
        foreach ([$otherEvent, $expired] as $user) {
            Mail::assertQueued(NewAnnouncementMail::class, fn ($mail) => $mail->hasTo($user->email));
        }
    }

    private function context(Conference|ScheduledConference|null $context = null): void
    {
        app()->setCurrentConferenceId($context instanceof ScheduledConference ? $context->conference_id : ($context?->id ?? 0));
        app()->setCurrentScheduledConferenceId($context instanceof ScheduledConference ? $context->id : 0);
    }

    private function member(): User
    {
        return $this->staff(UserRole::Author);
    }

    private function staff(UserRole $name): User
    {
        $conferenceId = $name === UserRole::Admin ? 0 : app()->getCurrentConferenceId();
        $eventId = in_array($name, [UserRole::Admin, UserRole::ConferenceManager])
            ? 0 : (app()->getCurrentScheduledConferenceId() ?: 0);
        $role = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => $name->value,
            'guard_name' => 'web',
            'conference_id' => $conferenceId,
            'scheduled_conference_id' => $eventId,
        ]);
        $user = User::factory()->create(['password' => 'password']);
        $user->assignRole($role);

        return $user;
    }

    private function seedBan(User $user, UserBanScope $scope, array $attributes = []): Ban
    {
        $ban = $user->bans()->create([...$attributes, ...$scope->attributes()]);
        $user->unsetRelation('bans');

        return $ban;
    }

    private function preparePanelPermissions(): void
    {
        foreach (['viewAny', 'invite', 'update', 'delete', 'loginAs', 'enable', 'disable', 'sendEmail', 'create', 'accessAdministration'] as $action) {
            Permission::firstOrCreate(['name' => 'User:'.$action, 'guard_name' => 'web']);
        }
    }
}
