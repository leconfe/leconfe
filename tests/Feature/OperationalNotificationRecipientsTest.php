<?php

namespace Tests\Feature;

use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\PaymentFee;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Notifications\NewSubmission;
use App\Notifications\ParticipantRegistered;
use App\Notifications\SubmissionWithdrawRequested;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\ReviewStep;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Services\Notifications\OperationalNotificationRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalNotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private Conference $conference;

    private ScheduledConference $scheduledConference;

    private Track $track;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/submissions', fn () => null)
            ->name('filament.conference.resources.submissions.index');
        Route::get('/test/submissions/{record}', fn () => null)
            ->name('filament.conference.resources.submissions.view');
        Route::get('/test/submissions/complete/{record}', fn () => null)
            ->name('filament.conference.resources.submissions.complete');
        Route::get('/test/payments/{record}', fn () => null)
            ->name('filament.conference.pages.payment-detail');

        $this->conference = Conference::factory()->create([
            'path' => 'operational-notifications',
        ]);
        $this->scheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $this->conference->getKey(),
            'path' => 'operational-notifications-2026',
        ]);

        app()->setCurrentConferenceId($this->conference->getKey());
        app()->setCurrentScheduledConferenceId($this->scheduledConference->getKey());

        $this->track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'General Track',
            'abbreviation' => 'GEN',
            'is_active' => true,
        ]);
    }

    public function test_operational_recipients_exclude_admin_role_targets(): void
    {
        $admin = $this->userWithRole(UserRole::Admin, 'admin@example.test');
        $manager = $this->userWithRole(UserRole::ConferenceManager, 'manager@example.test');

        $recipientIds = app(OperationalNotificationRecipients::class)
            ->forRoles([UserRole::Admin, UserRole::ConferenceManager])
            ->pluck('id');

        $this->assertFalse($recipientIds->contains($admin->getKey()));
        $this->assertTrue($recipientIds->contains($manager->getKey()));
    }

    public function test_operational_recipients_accept_enum_and_string_roles(): void
    {
        $manager = $this->userWithRole(UserRole::ConferenceManager, 'manager@example.test');

        $enumRecipientIds = app(OperationalNotificationRecipients::class)
            ->forRoles([UserRole::ConferenceManager])
            ->pluck('id')
            ->all();

        $stringRecipientIds = app(OperationalNotificationRecipients::class)
            ->forRoles([UserRole::ConferenceManager->value])
            ->pluck('id')
            ->all();

        $this->assertSame([$manager->getKey()], $enumRecipientIds);
        $this->assertSame($enumRecipientIds, $stringRecipientIds);
    }

    public function test_operational_recipients_can_dedupe_merged_user_lists(): void
    {
        $manager = $this->userWithRole(UserRole::ConferenceManager, 'manager@example.test');
        $editor = $this->userWithRole(UserRole::ScheduledConferenceEditor, 'editor@example.test');

        $recipientIds = app(OperationalNotificationRecipients::class)
            ->uniqueUsers([$manager, $editor, $manager, $editor])
            ->pluck('id')
            ->all();

        $this->assertSame([$manager->getKey(), $editor->getKey()], $recipientIds);
    }

    public function test_participant_registration_notifies_manager_but_not_admin(): void
    {
        $admin = $this->userWithRole(UserRole::Admin, 'admin@example.test');
        $manager = $this->userWithRole(UserRole::ConferenceManager, 'manager@example.test');
        $participant = $this->userWithRole(UserRole::Participant, 'participant@example.test');
        $paymentFee = $this->createPaymentFee(PaymentManager::TYPE_PARTICIPANT_FEE);

        $this->scheduledConference->setMeta('participant_payment', true);
        Notification::fake();

        $this->actingAs($participant);

        Livewire::test(ParticipantRegistration::class)
            ->set('formData.given_name', 'Participant')
            ->set('formData.family_name', 'Tester')
            ->set('formData.payment_fee_id', $paymentFee->getKey())
            ->call('submit');

        Notification::assertSentTo($manager, ParticipantRegistered::class);
        Notification::assertNotSentTo($admin, ParticipantRegistered::class);
    }

    public function test_new_submission_fallback_notifies_manager_but_not_admin(): void
    {
        $admin = $this->userWithRole(UserRole::Admin, 'admin@example.test');
        $manager = $this->userWithRole(UserRole::ConferenceManager, 'manager@example.test');
        $author = $this->userWithRole(UserRole::Author, 'author@example.test');
        $submission = $this->createSubmission($author, [
            'stage' => SubmissionStage::Wizard,
            'status' => SubmissionStatus::Incomplete,
        ]);

        Notification::fake();

        $this->actingAs($author);

        Livewire::test(ReviewStep::class, ['record' => $submission])
            ->callAction('submitAction');

        Notification::assertSentTo($manager, NewSubmission::class);
        Notification::assertNotSentTo($admin, NewSubmission::class);
    }

    public function test_withdrawal_request_notifies_unique_operational_recipients_but_not_admin(): void
    {
        $admin = $this->userWithRole(UserRole::Admin, 'admin@example.test');
        $manager = $this->userWithRole(UserRole::ConferenceManager, 'manager@example.test');
        $author = $this->userWithRole(UserRole::Author, 'author@example.test');
        $submission = $this->createSubmission($author, [
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $submission->participants()->create([
            'user_id' => $manager->getKey(),
            'role_id' => $this->role(UserRole::ConferenceManager)->getKey(),
        ]);

        Notification::fake();

        $this->actingAs($author);

        $this->callViewSubmissionHeaderAction($submission, 'request_withdraw', [
            'reason' => 'Author requested withdrawal.',
        ]);

        $this->assertCount(1, Notification::sent($manager, SubmissionWithdrawRequested::class));
        Notification::assertNotSentTo($admin, SubmissionWithdrawRequested::class);
    }

    private function role(UserRole $role): Role
    {
        $attributes = [
            'name' => $role->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ];

        if ($role === UserRole::ConferenceManager) {
            $attributes['conference_id'] = $this->conference->getKey();
        }

        if (in_array($role, UserRole::scheduledConferenceRoles(), true)) {
            $attributes['conference_id'] = $this->conference->getKey();
            $attributes['scheduled_conference_id'] = $this->scheduledConference->getKey();
        }

        return Role::withoutGlobalScopes()->firstOrCreate($attributes);
    }

    private function userWithRole(UserRole $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password123456',
        ]);

        $user->assignRole($this->role($role));

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function createSubmission(User $author, array $state): Submission
    {
        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $this->track->getKey(),
            ...$state,
        ]);
        $submission->setMeta('title', 'Operational Notification Submission');

        return $submission->refresh();
    }

    private function createPaymentFee(int $type): PaymentFee
    {
        return PaymentFee::withoutGlobalScopes()->create([
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'name' => 'Registration Fee',
            'type' => $type,
            'amount' => 100,
            'currency' => 'usd',
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function callViewSubmissionHeaderAction(Submission $submission, string $actionName, array $data): void
    {
        $page = app(ViewSubmission::class);
        $page->record = $submission;

        $method = new \ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);

        $action = collect($method->invoke($page))
            ->first(fn ($action): bool => $action->getName() === $actionName);

        $this->assertNotNull($action);

        try {
            $action
                ->livewire($page)
                ->call(['data' => $data]);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $exception) {
            if (! str_contains($exception->getMessage(), 'filament.conference.resources.submissions.view')) {
                throw $exception;
            }
        }
    }
}
