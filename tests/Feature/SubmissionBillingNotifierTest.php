<?php

namespace Tests\Feature;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\PaymentFee;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Notifications\SubmissionPayment;
use App\Services\Billing\SubmissionBillingNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubmissionBillingNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_submission_invoice_once_when_stage_threshold_is_reached(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::CallforAbstract,
            submissionStatus: SubmissionStatus::Queued,
        );

        Notification::fake();

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        Notification::assertNothingSent();

        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ], $context['submission']);

        $this->assertCount(1, Notification::sent($context['user'], SubmissionPayment::class));

        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ], $context['submission']);

        $this->assertCount(1, Notification::sent($context['user'], SubmissionPayment::class));

        $context['submission']->refresh();

        $this->assertNotNull(
            $context['submission']->payment?->getMeta(SubmissionBillingNotifier::PAYMENT_META_AUTO_NOTIFIED_AT)
        );
    }

    public function test_it_sends_invoice_immediately_for_late_payment_when_threshold_already_passed(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::Presentation,
            submissionStatus: SubmissionStatus::OnPresentation,
        );

        Notification::fake();

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $this->assertCount(1, Notification::sent($context['user'], SubmissionPayment::class));
    }

    public function test_it_respects_scheduled_conference_specific_billing_stage(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Conference',
            'path' => 'conference',
        ]);

        $scA = $this->createScheduledConference($conference, 'sc-a');
        $scB = $this->createScheduledConference($conference, 'sc-b');

        $scA->setManyMeta([
            'submission_payment' => true,
            'submission_billing_stage' => SubmissionStage::PeerReview->value,
        ]);

        $scB->setManyMeta([
            'submission_payment' => true,
            'submission_billing_stage' => SubmissionStage::Presentation->value,
        ]);

        $contextA = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
            conference: $conference,
            scheduledConference: $scA,
            userEmail: 'author-a@example.test',
        );

        $contextB = $this->makeSubmissionContext(
            billingStage: SubmissionStage::Presentation,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
            conference: $conference,
            scheduledConference: $scB,
            userEmail: 'author-b@example.test',
        );

        Notification::fake();

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scA->getKey());
        $this->queueSubmissionPayment($contextA['submission'], $contextA['paymentFee']);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scB->getKey());
        $this->queueSubmissionPayment($contextB['submission'], $contextB['paymentFee']);

        $this->assertCount(1, Notification::sent($contextA['user'], SubmissionPayment::class));
        Notification::assertNotSentTo($contextB['user'], SubmissionPayment::class);

        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ], $contextB['submission']);

        $this->assertCount(1, Notification::sent($contextB['user'], SubmissionPayment::class));
    }

    protected function makeSubmissionContext(
        SubmissionStage $billingStage,
        SubmissionStage $submissionStage,
        SubmissionStatus $submissionStatus,
        ?Conference $conference = null,
        ?ScheduledConference $scheduledConference = null,
        string $userEmail = 'author@example.test',
    ): array {
        $conference ??= Conference::query()->create([
            'name' => 'Conference '.uniqid(),
            'path' => 'conf-'.uniqid(),
        ]);

        $scheduledConference ??= $this->createScheduledConference($conference, 'sc-'.uniqid());

        $scheduledConference->setManyMeta([
            'submission_payment' => true,
            'submission_billing_stage' => $billingStage->value,
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'Track '.uniqid(),
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => $userEmail,
            'password' => 'password123456',
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $user->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => $submissionStage,
            'status' => $submissionStatus,
        ]);

        $submission->setMeta('title', 'Submission '.uniqid());

        $paymentFee = PaymentFee::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'name' => 'Submission Fee',
            'type' => PaymentManager::TYPE_SUBMISSION_FEE,
            'amount' => 100,
            'currency' => 'usd',
            'is_active' => true,
        ]);

        return [
            'conference' => $conference,
            'scheduledConference' => $scheduledConference,
            'track' => $track,
            'user' => $user,
            'submission' => $submission,
            'paymentFee' => $paymentFee,
        ];
    }

    protected function createScheduledConference(Conference $conference, string $path): ScheduledConference
    {
        return ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => strtoupper($path),
            'path' => $path,
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);
    }

    protected function queueSubmissionPayment(Submission $submission, PaymentFee $paymentFee): void
    {
        PaymentManager::get()->queue(
            $submission,
            $paymentFee,
            $submission->user,
            PaymentManager::TYPE_SUBMISSION_FEE,
            $submission->getMeta('title'),
            '/submission/'.$submission->getKey(),
            'Submission billing',
        );
    }
}
