<?php

namespace Tests\Feature;

use App\Actions\Announcements\AnnouncementBroadcastMail;
use App\Classes\Log;
use App\Listeners\FilterMutedEmailRecipients;
use App\Livewire\EmailPreferencesForm;
use App\Mail\Templates;
use App\Mail\Templates\NewAnnouncementMail;
use App\Mail\Templates\SubmissionWithdrawnRequestMail;
use App\Models\Announcement;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\Administration\Pages\Profile;
use App\Services\EmailPreferences\Categories;
use App\Services\EmailPreferences\ContextAccess;
use App\Services\EmailPreferences\Preferences;
use Filament\Facades\Filament;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private Conference $conference;

    private ScheduledConference $scheduledConference;

    private ScheduledConference $siblingScheduledConference;

    private Conference $otherConference;

    private ScheduledConference $otherScheduledConference;

    private Preferences $preferences;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = Conference::factory()->create(['name' => 'Conference A', 'path' => 'email-preferences-a']);
        $this->scheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $this->conference->getKey(),
            'title' => 'A 2026',
            'path' => 'email-preferences-a-2026',
        ]);
        $this->siblingScheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $this->conference->getKey(),
            'title' => 'A 2027',
            'path' => 'email-preferences-a-2027',
        ]);
        $this->otherConference = Conference::factory()->create(['name' => 'Conference B', 'path' => 'email-preferences-b']);
        $this->otherScheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $this->otherConference->getKey(),
            'title' => 'B 2026',
            'path' => 'email-preferences-b-2026',
        ]);

        app()->setCurrentConferenceId($this->conference->getKey());
        app()->setCurrentScheduledConferenceId($this->scheduledConference->getKey());

        Filament::setCurrentPanel(Filament::getPanel('scheduledConference'));

        $this->preferences = app(Preferences::class);
    }

    public function test_operational_categories_are_enabled_by_default(): void
    {
        $user = $this->editor('editor@example.test');

        foreach (Categories::all() as $category) {
            if ($category === Categories::ANNOUNCEMENT) {
                continue;
            }

            $this->assertTrue($this->enabled($user, $category), "{$category} should be enabled by default.");
            $this->assertNull($this->preferences->explicit($user, $this->conference->getKey(), $this->scheduledConference->getKey(), $category));
        }
    }

    public function test_announcement_default_follows_the_legacy_global_opt_in(): void
    {
        $user = $this->editor('editor@example.test');

        $user->setMeta('enable_new_announcement_email', true);
        $this->assertTrue($this->enabled($user, Categories::ANNOUNCEMENT));

        $user->setMeta('enable_new_announcement_email', false);
        $this->assertFalse($this->enabled($user, Categories::ANNOUNCEMENT));

        $user->removeMeta('enable_new_announcement_email');
        $this->assertFalse($this->enabled($user, Categories::ANNOUNCEMENT), 'Missing legacy value stays unsubscribed.');
    }

    public function test_scoped_announcement_choice_overrides_legacy_value_without_rewriting_it(): void
    {
        $user = $this->editor('editor@example.test');
        $user->setMeta('enable_new_announcement_email', false);

        $this->save($user, [Categories::ANNOUNCEMENT => true]);

        $this->assertTrue($this->enabled($user, Categories::ANNOUNCEMENT));
        $this->assertFalse($this->enabled($user, Categories::ANNOUNCEMENT, $this->siblingScheduledConference));
        $this->assertFalse($user->meta()->where('key', 'enable_new_announcement_email')->first()->value);
    }

    public function test_preferences_are_stored_with_the_documented_metadata_key(): void
    {
        $user = $this->editor('editor@example.test');

        $this->save($user, ['new_submission' => false]);

        $this->assertDatabaseHas('meta', [
            'metable_type' => $user->getMorphClass(),
            'metable_id' => $user->getKey(),
            'key' => "email_preferences.{$this->conference->getKey()}.{$this->scheduledConference->getKey()}.new_submission",
        ]);
        $this->assertFalse($this->preferences->explicit($user, $this->conference->getKey(), $this->scheduledConference->getKey(), 'new_submission'));
    }

    public function test_preferences_are_isolated_per_user_and_scheduled_conference(): void
    {
        $user = $this->manager('manager@example.test');
        $other = $this->editor('editor@example.test');

        $this->save($user, ['new_submission' => false, 'payment_proof' => false]);

        $this->assertFalse($this->enabled($user, 'new_submission'));
        $this->assertFalse($this->enabled($user, 'payment_proof'));
        $this->assertTrue($this->enabled($user, 'review_completed'));
        $this->assertTrue($this->enabled($user, 'new_submission', $this->siblingScheduledConference));
        $this->assertTrue($this->enabled($user, 'new_submission', $this->otherScheduledConference));
        $this->assertTrue($this->enabled($other, 'new_submission'));

        $this->save($user, ['new_submission' => false], $this->siblingScheduledConference);
        $this->save($user, ['new_submission' => true]);

        $this->assertTrue($this->enabled($user, 'new_submission'));
        $this->assertFalse($this->enabled($user, 'new_submission', $this->siblingScheduledConference));
    }

    public function test_conference_wide_metadata_is_ignored(): void
    {
        $user = $this->editor('editor@example.test');
        $user->setMeta(Preferences::key($this->conference->getKey(), 0, 'new_submission'), false);

        $this->assertTrue($this->enabled($user, 'new_submission'));
    }

    public function test_context_access_follows_membership(): void
    {
        $manager = $this->manager('manager@example.test');
        $editor = $this->editor('editor@example.test');
        $admin = $this->userWithRole('admin@example.test', $this->role(UserRole::Admin, 0, 0));
        $outsider = User::factory()->create(['email' => 'outsider@example.test', 'password' => 'password123456']);
        $access = app(ContextAccess::class);

        $this->assertEqualsCanonicalizing(
            [$this->scope($this->scheduledConference), $this->scope($this->siblingScheduledConference)],
            array_keys($access->contexts($manager)),
        );
        $this->assertSame([$this->scope($this->scheduledConference)], array_keys($access->contexts($editor)));
        $this->assertSame([], $access->contexts($outsider));
        $this->assertTrue($access->allows($admin, $this->otherConference->getKey(), $this->otherScheduledConference->getKey()));
        $this->assertFalse($access->allows($editor, $this->conference->getKey(), $this->siblingScheduledConference->getKey()));
        $this->assertFalse($access->allows($manager, $this->otherConference->getKey(), $this->otherScheduledConference->getKey()));
    }

    #[DataProvider('invalidScopes')]
    public function test_saving_rejects_inaccessible_scopes(string $userKey, string $scope): void
    {
        $users = [
            'manager' => fn () => $this->manager('manager@example.test'),
            'editor' => fn () => $this->editor('editor@example.test'),
            'admin' => fn () => $this->userWithRole('admin@example.test', $this->role(UserRole::Admin, 0, 0)),
            'outsider' => fn () => User::factory()->create(['email' => 'outsider@example.test', 'password' => 'password123456']),
        ];
        $user = $users[$userKey]();

        [$conferenceId, $scheduledConferenceId] = match ($scope) {
            'conference-wide' => [$this->conference->getKey(), 0],
            'negative' => [$this->conference->getKey(), -1],
            'zero-conference' => [0, $this->scheduledConference->getKey()],
            'wrong-parent' => [$this->conference->getKey(), $this->otherScheduledConference->getKey()],
            'sibling' => [$this->conference->getKey(), $this->siblingScheduledConference->getKey()],
            'other-conference' => [$this->otherConference->getKey(), $this->otherScheduledConference->getKey()],
            'member' => [$this->conference->getKey(), $this->scheduledConference->getKey()],
        };

        $this->assertForbidden(fn () => $this->preferences->save($user, $conferenceId, $scheduledConferenceId, ['new_submission' => false]));
        $this->assertNull($user->meta()->where('key', 'like', 'email_preferences.%')->first());
    }

    public static function invalidScopes(): array
    {
        return [
            'conference-wide scope' => ['manager', 'conference-wide'],
            'negative scheduled conference' => ['manager', 'negative'],
            'zero conference' => ['admin', 'zero-conference'],
            'wrong parent conference, even for admins' => ['admin', 'wrong-parent'],
            'sibling event without membership' => ['editor', 'sibling'],
            'other conference' => ['manager', 'other-conference'],
            'no membership' => ['outsider', 'member'],
        ];
    }

    public function test_saving_rejects_unknown_categories_and_non_boolean_values(): void
    {
        $user = $this->editor('editor@example.test');

        foreach ([['unknown' => false], ['new_submission' => false, 'payment_proof' => 'invalid']] as $values) {
            try {
                $this->save($user, $values);
                $this->fail('Invalid preferences were accepted.');
            } catch (ValidationException) {
                //
            }
        }

        $this->assertNull($this->preferences->explicit($user, $this->conference->getKey(), $this->scheduledConference->getKey(), 'new_submission'));
    }

    #[DataProvider('mailCategories')]
    public function test_each_category_is_filtered_by_preference(string $mailable, string $category): void
    {
        $user = $this->editor('editor@example.test');
        $user->setMeta('enable_new_announcement_email', true);

        // Scope comes from the mail subject, not from the current panel.
        app()->setCurrentConferenceId($this->otherConference->getKey());
        app()->setCurrentScheduledConferenceId($this->otherScheduledConference->getKey());

        $this->assertNull($this->filter($this->mailEvent($mailable, [$user->email])));

        $this->save($user, [$category => false]);
        $this->assertFalse($this->filter($this->mailEvent($mailable, [$user->email])));

        $this->save($user, [$category => true]);
        $this->assertNull($this->filter($this->mailEvent($mailable, [$user->email])));
    }

    public static function mailCategories(): array
    {
        return collect(Categories::MAILS)
            ->mapWithKeys(fn (string $category, string $mailable) => [$category => [$mailable, $category]])
            ->all();
    }

    public function test_unsupported_and_external_mail_is_not_filtered(): void
    {
        $user = $this->editor('editor@example.test');
        $this->save($user, array_fill_keys(Categories::all(), false));

        $this->assertNull($this->filter($this->mailEvent(Templates\VerifyUserEmail::class, [$user->email])));
        $this->assertNull($this->filter($this->mailEvent(Templates\PaymentConfirmedMail::class, [$user->email])));
        $this->assertNull($this->filter($this->mailEvent(Templates\NewSubmissionMail::class, ['external@example.test'])));
    }

    public function test_to_cc_and_bcc_recipients_are_filtered_individually(): void
    {
        $muted = $this->editor('muted@example.test');
        $subscribed = $this->editor('subscribed@example.test');
        $this->save($muted, ['review_completed' => false]);

        $event = $this->mailEvent(Templates\ReviewCompleteMail::class, [$muted->email]);
        $event->message
            ->addTo($subscribed->email)
            ->addCc(strtoupper($subscribed->email))
            ->addCc($muted->email)
            ->addBcc('external@example.test');

        $this->assertNull($this->filter($event));
        $this->assertSame([$subscribed->email], array_map(fn ($address) => $address->getAddress(), $event->message->getTo()));
        $this->assertSame([], $event->message->getCc());
        $this->assertSame(['external@example.test'], array_map(fn ($address) => $address->getAddress(), $event->message->getBcc()));
    }

    public function test_muted_template_mail_is_not_delivered(): void
    {
        $muted = $this->manager('muted@example.test');
        $subscribed = $this->editor('subscribed@example.test');
        $submission = $this->createSubmission($subscribed);
        $this->save($muted, ['withdrawal_requested' => false]);

        Mail::to([$muted->email, $subscribed->email])->send(new SubmissionWithdrawnRequestMail($submission));
        Mail::to($muted->email)->send(new SubmissionWithdrawnRequestMail($submission));

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertSame([$subscribed->email], array_map(fn ($address) => $address->getAddress(), $messages[0]->getTo()));
    }

    public function test_queued_mail_reads_preferences_at_send_time(): void
    {
        $user = $this->editor('editor@example.test');
        $submission = $this->createSubmission($user);

        $payload = serialize(new SendQueuedMailable((new SubmissionWithdrawnRequestMail($submission))->to($user->email)));
        $user->load('meta');
        $this->save($user, ['withdrawal_requested' => false]);

        unserialize($payload)->handle(app(MailFactory::class));
        $this->assertCount(0, $this->sentMessages());

        $this->save($user, ['withdrawal_requested' => true]);

        unserialize($payload)->handle(app(MailFactory::class));
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_announcement_broadcast_uses_scheduled_conference_preference(): void
    {
        $optedIn = $this->editor('opted-in@example.test');
        $optedIn->setMeta('enable_new_announcement_email', false);
        $this->save($optedIn, [Categories::ANNOUNCEMENT => true]);

        $optedOut = $this->editor('opted-out@example.test');
        $optedOut->setMeta('enable_new_announcement_email', true);
        $this->save($optedOut, [Categories::ANNOUNCEMENT => false]);

        $legacy = $this->editor('legacy@example.test');
        $legacy->setMeta('enable_new_announcement_email', true);

        $legacyOptOut = $this->editor('legacy-opt-out@example.test');
        $legacyOptOut->setMeta('enable_new_announcement_email', false);

        $announcement = Announcement::withoutGlobalScopes()->forceCreate([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'Program update',
        ]);

        Mail::fake();

        app(AnnouncementBroadcastMail::class)->handle($announcement);

        $recipients = Mail::queued(NewAnnouncementMail::class)
            ->flatMap(fn (NewAnnouncementMail $mail) => array_column($mail->to, 'address'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$legacy->email, $optedIn->email], $recipients);
    }

    public function test_form_loads_and_saves_preferences_for_the_selected_scheduled_conference(): void
    {
        $user = $this->manager('manager@example.test');
        $user->setMeta('enable_new_announcement_email', false);
        $this->save($user, ['new_submission' => false], $this->siblingScheduledConference);

        $component = Livewire::actingAs($user)
            ->test(EmailPreferencesForm::class)
            ->assertSet('data.scope', $this->scope($this->scheduledConference))
            ->assertSet('data.preferences.new_submission', true)
            ->assertSet('data.preferences.'.Categories::ANNOUNCEMENT, false)
            ->set('data.preferences.payment_proof', false)
            ->set('data.preferences.'.Categories::ANNOUNCEMENT, true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertCount(count(Categories::all()), $component->get('data.preferences'));
        $this->assertFalse($this->enabled($user, 'payment_proof'));
        $this->assertTrue($this->enabled($user, Categories::ANNOUNCEMENT));
        $this->assertTrue($this->enabled($user, 'payment_proof', $this->siblingScheduledConference));
        $this->assertFalse($user->meta()->where('key', 'enable_new_announcement_email')->first()->value);

        $component
            ->set('data.scope', $this->scope($this->siblingScheduledConference))
            ->assertSet('data.preferences.new_submission', false)
            ->assertSet('data.preferences.payment_proof', true);
    }

    public function test_form_rejects_forged_scopes_and_revoked_membership(): void
    {
        $user = $this->editor('editor@example.test');

        $component = Livewire::actingAs($user)->test(EmailPreferencesForm::class);

        $component
            ->set('data.scope', $this->scope($this->otherScheduledConference))
            ->assertForbidden();

        $component = Livewire::actingAs($user)->test(EmailPreferencesForm::class);

        $component
            ->set('data.scope', $this->conference->getKey().':0')
            ->assertForbidden();

        $component = Livewire::actingAs($user)->test(EmailPreferencesForm::class);
        $user->roles()->detach();

        $component
            ->set('data.preferences.new_submission', false)
            ->call('save')
            ->assertForbidden();

        $this->assertNull($this->preferences->explicit($user, $this->conference->getKey(), $this->scheduledConference->getKey(), 'new_submission'));
    }

    public function test_form_shows_empty_state_without_memberships(): void
    {
        $user = User::factory()->create(['email' => 'outsider@example.test', 'password' => 'password123456']);

        Livewire::actingAs($user)
            ->test(EmailPreferencesForm::class)
            ->assertSee(__('email_preferences.empty'));
    }

    public function test_profile_notifications_tab_renders_the_scheduled_conference_form(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('administration'));

        $user = $this->editor('editor@example.test');

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->assertSeeLivewire(EmailPreferencesForm::class)
            ->assertDontSee('meta.enable_new_announcement_email');
    }

    private function enabled(User $user, string $category, ?ScheduledConference $scheduledConference = null): bool
    {
        $scheduledConference ??= $this->scheduledConference;

        return $this->preferences->enabled($user, $scheduledConference->conference_id, $scheduledConference->getKey(), $category);
    }

    private function save(User $user, array $values, ?ScheduledConference $scheduledConference = null): void
    {
        $scheduledConference ??= $this->scheduledConference;

        $this->preferences->save($user, $scheduledConference->conference_id, $scheduledConference->getKey(), $values);
    }

    private function scope(ScheduledConference $scheduledConference): string
    {
        return $scheduledConference->conference_id.':'.$scheduledConference->getKey();
    }

    private function filter(MessageSending $event): ?bool
    {
        return app(FilterMutedEmailRecipients::class)->handle($event);
    }

    /**
     * @param  array<int, string>  $recipients
     */
    private function mailEvent(string $mailable, array $recipients): MessageSending
    {
        $submission = (new Submission)->forceFill([
            'id' => 1,
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
        ]);

        $message = (new Email)
            ->from('site@example.test')
            ->to(...$recipients)
            ->subject('Fixture')
            ->text('Fixture');

        return new MessageSending($message, [
            'mailableClass' => $mailable,
            'log' => Log::make($submission, 'email', 'Fixture'),
        ]);
    }

    /**
     * @return array<int, Email>
     */
    private function sentMessages(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($message) => $message->getOriginalMessage())
            ->values()
            ->all();
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            return;
        }

        $this->fail('Expected a 403 response.');
    }

    private function role(UserRole $role, int $conferenceId, int $scheduledConferenceId): Role
    {
        return Role::withoutGlobalScopes()->firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
            'conference_id' => $conferenceId,
            'scheduled_conference_id' => $scheduledConferenceId,
        ]);
    }

    private function manager(string $email): User
    {
        return $this->userWithRole($email, $this->role(UserRole::ConferenceManager, $this->conference->getKey(), 0));
    }

    private function editor(string $email): User
    {
        return $this->userWithRole($email, $this->role(
            UserRole::ScheduledConferenceEditor,
            $this->conference->getKey(),
            $this->scheduledConference->getKey(),
        ));
    }

    private function userWithRole(string $email, Role $role): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password123456',
        ]);

        $user->assignRole($role);

        return $user->refresh();
    }

    private function createSubmission(User $author): Submission
    {
        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'General Track',
            'abbreviation' => 'GEN',
            'is_active' => true,
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $submission->setMeta('title', 'Email Preferences Submission');

        return $submission->refresh();
    }
}
