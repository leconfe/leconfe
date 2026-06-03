<?php

namespace Tests\Feature;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Participant;
use App\Models\PaymentFee;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Notifications\ParticipantPayment;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
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

    public function test_it_does_not_send_submission_invoice_for_declined_or_withdrawn_submission(): void
    {
        $declined = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::Declined,
            userEmail: 'declined@example.test',
        );

        $withdrawn = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::Withdrawn,
            userEmail: 'withdrawn@example.test',
        );

        Notification::fake();

        $this->queueSubmissionPayment($declined['submission'], $declined['paymentFee']);
        $this->queueSubmissionPayment($withdrawn['submission'], $withdrawn['paymentFee']);

        Notification::assertNothingSent();
    }

    public function test_manual_submission_invoice_does_not_generate_when_payment_is_created(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'submission_payment_auto_notify' => false,
            'invoice_enable' => true,
            'invoice_prefix_number' => 'INV-',
            'invoice_number' => 7,
            'invoice_suffix_number' => '-SC',
        ]);

        Notification::fake();

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();

        $this->assertNull($payment->invoice);
        $this->assertSame(7, $context['scheduledConference']->getLatestInvoiceNumber());
        Notification::assertNothingSent();

        $this->assertTrue($payment->ensureInvoice());
        $this->assertSame('INV-007-SC', $payment->refresh()->invoice);
        $this->assertSame(8, $context['scheduledConference']->refresh()->getLatestInvoiceNumber());
    }

    public function test_manual_submission_invoice_generation_can_assign_invoice_to_existing_payment(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'submission_payment_auto_notify' => false,
            'invoice_enable' => false,
            'invoice_prefix_number' => 'INV-',
            'invoice_number' => 7,
            'invoice_suffix_number' => '-SC',
        ]);

        Notification::fake();

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();

        $this->assertNull($payment->invoice);
        Notification::assertNothingSent();

        $context['scheduledConference']->setMeta('invoice_enable', true);

        $this->assertTrue($payment->ensureInvoice());
        $this->assertSame('INV-007-SC', $payment->refresh()->invoice);
        $this->assertSame(8, $context['scheduledConference']->refresh()->getLatestInvoiceNumber());
        $this->assertFalse($payment->ensureInvoice());
        $this->assertSame(8, $context['scheduledConference']->refresh()->getLatestInvoiceNumber());
    }

    public function test_submission_payment_fee_can_be_changed_without_participant_notification_field(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $newPaymentFee = PaymentFee::withoutGlobalScopes()->create([
            'conference_id' => $context['conference']->getKey(),
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Updated Submission Fee',
            'type' => PaymentManager::TYPE_SUBMISSION_FEE,
            'amount' => 250,
            'currency' => 'usd',
            'is_active' => true,
        ]);

        $payment = $context['submission']->payment()->firstOrFail();

        PaymentDetail::updatePaymentFeeRecord($payment, [
            'payment_fee_id' => $newPaymentFee->getKey(),
            'additional_items' => [],
        ]);

        $payment->refresh();

        $this->assertSame($newPaymentFee->getKey(), $payment->payment_fee_id);
        $this->assertSame(250.0, (float) $payment->amount);
        $this->assertSame(250.0, (float) $payment->getMeta('base_amount'));
    }

    public function test_manual_submission_invoice_allows_payment_detail_before_billing_stage(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::Presentation,
            submissionStage: SubmissionStage::CallforAbstract,
            submissionStatus: SubmissionStatus::Queued,
        );

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();

        $this->assertFalse(
            app(SubmissionBillingNotifier::class)->isSubmissionPaymentAvailable($context['submission'], $context['scheduledConference'])
        );
        $this->assertFalse((bool) app(\App\Policies\PaymentPolicy::class)->view($context['user'], $payment));

        $payment->update(['invoice' => 'INV-001']);

        $this->assertTrue(
            app(SubmissionBillingNotifier::class)->canViewSubmissionPaymentDetail(
                $context['submission'],
                $payment->refresh(),
                $context['scheduledConference'],
            )
        );
        $this->assertTrue(app(\App\Policies\PaymentPolicy::class)->view($context['user'], $payment));
        $this->assertTrue(PaymentDetail::canUsePaymentMethodActions($payment));
    }

    public function test_manual_submission_invoice_keeps_detail_view_only_for_declined_or_withdrawn_submission(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::Presentation,
            submissionStage: SubmissionStage::CallforAbstract,
            submissionStatus: SubmissionStatus::Declined,
        );

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();
        $payment->update(['invoice' => 'INV-001']);

        $this->assertTrue(app(\App\Policies\PaymentPolicy::class)->view($context['user'], $payment));
        $this->assertFalse(PaymentDetail::canUsePaymentMethodActions($payment));

        $context['submission']->update(['status' => SubmissionStatus::Withdrawn]);

        $this->assertTrue(app(\App\Policies\PaymentPolicy::class)->view($context['user'], $payment));
        $this->assertFalse(PaymentDetail::canUsePaymentMethodActions($payment->refresh()));
    }

    public function test_submission_payment_notification_builds_payment_link_without_current_context(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->with('scheduledConference.conference')->firstOrFail();
        $payment->update(['invoice' => 'INV-001']);
        $context['submission']->setRelation('payment', $payment);

        (function () {
            $this->currentConferenceId = null;
            $this->currentConference = null;
            $this->currentScheduledConferenceId = null;
            $this->currentScheduledConference = null;
        })->call(app());

        $url = $payment->getPaymentDetailUrl();
        $notification = new SubmissionPayment($context['submission']);
        $databaseMessage = $notification->toDatabase($context['user']);

        $this->assertSame(
            route('filament.scheduledConference.pages.payment-detail', [
                'conference' => $context['conference']->path,
                'serie' => $context['scheduledConference']->path,
                'record' => $payment,
            ]),
            $url
        );
        $this->assertSame($url, data_get($databaseMessage, 'actions.0.url'));
        $this->assertSame($url, data_get($notification->toMail($context['user'])->buildViewData(), 'Payment Link'));
    }

    public function test_participant_payment_notification_builds_payment_link_without_current_context(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $participant = Participant::withoutGlobalScopes()->create([
            'given_name' => 'Participant',
            'family_name' => 'Tester',
            'email' => 'participant@example.test',
            'conference_id' => $context['conference']->getKey(),
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
        ]);

        $paymentFee = PaymentFee::withoutGlobalScopes()->create([
            'conference_id' => $context['conference']->getKey(),
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Participant Fee',
            'type' => PaymentManager::TYPE_PARTICIPANT_FEE,
            'amount' => 150,
            'currency' => 'usd',
            'is_active' => true,
        ]);

        $payment = PaymentManager::get()->queue(
            $participant,
            $paymentFee,
            $context['user'],
            PaymentManager::TYPE_PARTICIPANT_FEE,
            $participant->full_name,
            '/participant/'.$participant->getKey(),
            'Participant billing',
        )->loadMissing(['scheduledConference.conference', 'fee']);

        $payment->update(['invoice' => 'INV-002']);
        $participant->setRelation('payment', $payment);

        (function () {
            $this->currentConferenceId = null;
            $this->currentConference = null;
            $this->currentScheduledConferenceId = null;
            $this->currentScheduledConference = null;
        })->call(app());

        $url = $payment->getPaymentDetailUrl();
        $notification = new ParticipantPayment($participant);
        $databaseMessage = $notification->toDatabase($context['user']);

        $this->assertSame(
            route('filament.scheduledConference.pages.payment-detail', [
                'conference' => $context['conference']->path,
                'serie' => $context['scheduledConference']->path,
                'record' => $payment,
            ]),
            $url
        );
        $this->assertSame($url, data_get($databaseMessage, 'actions.0.url'));
        $this->assertSame($url, data_get($notification->toMail($context['user'])->buildViewData(), 'Payment Link'));
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
