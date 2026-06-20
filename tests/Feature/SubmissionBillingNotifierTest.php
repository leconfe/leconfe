<?php

namespace Tests\Feature;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Notifications\ParticipantPayment;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Livewire\InvoiceSetting;
use App\Panel\ScheduledConference\Livewire\ParticipantPaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentTable;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Panel\ScheduledConference\Widgets\Overview;
use App\Services\Billing\SubmissionBillingNotifier;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionBillingNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/payments/{record}', fn () => null)
            ->name('filament.conference.pages.payment-detail');
    }

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
        $this->assertTrue($context['submission']->payment->hasInvoiceBeenSent());
        $this->assertNotNull($context['submission']->payment->getMeta(Payment::INVOICE_SENT_AT_META));
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

    public function test_submission_invoice_does_not_show_submission_title_by_default(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->update(['is_published' => true]);
        $context['scheduledConference']->setMeta('invoice_enable', true);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();
        $payment->ensureInvoice();

        $this->actingAs($context['user']);

        $this->withoutVite()
            ->get($this->getInvoiceUrl($context, $payment))
            ->assertOk()
            ->assertSee('Submission Fee')
            ->assertDontSee($context['submission']->getMeta('title'));
    }

    public function test_submission_invoice_shows_submission_title_when_enabled(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->update(['is_published' => true]);
        $context['scheduledConference']->setManyMeta([
            'invoice_enable' => true,
            'invoice_show_submission_title' => true,
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();
        $payment->ensureInvoice();

        $this->actingAs($context['user']);

        $this->withoutVite()
            ->get($this->getInvoiceUrl($context, $payment))
            ->assertOk()
            ->assertSee('Submission Fee')
            ->assertSee($context['submission']->getMeta('title'));
    }

    public function test_receipt_can_be_generated_when_invoice_is_disabled(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'invoice_enable' => false,
            'receipt_enable' => true,
            'receipt_prefix_number' => 'RCP-',
            'receipt_number' => 7,
            'receipt_suffix_number' => '-SC',
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();

        $this->assertNull($payment->invoice);
        $this->assertSame('RCP-007-SC', $payment->receipt);
        $this->assertSame(8, $context['scheduledConference']->refresh()->getLatestReceiptNumber());
    }

    public function test_receipt_page_shows_receipt_notes(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->update(['is_published' => true]);
        $context['scheduledConference']->setManyMeta([
            'receipt_enable' => true,
            'receipt_notes' => '<p>Bring this receipt to check-in.</p>',
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();
        $payment->update([
            'paid_at' => now(),
            'receipt' => 'RCP-001',
        ]);

        $this->actingAs($context['user']);

        $this->withoutVite()
            ->get($this->getReceiptUrl($context, $payment))
            ->assertOk()
            ->assertSee('Bring this receipt to check-in.');
    }

    public function test_submission_receipt_does_not_show_submission_title_by_default(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->update(['is_published' => true]);
        $context['scheduledConference']->setMeta('receipt_enable', true);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();
        $payment->update([
            'paid_at' => now(),
            'receipt' => 'RCP-001',
        ]);

        $this->actingAs($context['user']);

        $this->withoutVite()
            ->get($this->getReceiptUrl($context, $payment))
            ->assertOk()
            ->assertDontSee($context['submission']->getMeta('title'));
    }

    public function test_submission_receipt_shows_submission_title_when_enabled(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->update(['is_published' => true]);
        $context['scheduledConference']->setManyMeta([
            'receipt_enable' => true,
            'receipt_show_submission_title' => true,
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();
        $payment->update([
            'paid_at' => now(),
            'receipt' => 'RCP-001',
        ]);

        $this->actingAs($context['user']);

        $this->withoutVite()
            ->get($this->getReceiptUrl($context, $payment))
            ->assertOk()
            ->assertSee($context['submission']->getMeta('title'));
    }

    public function test_receipt_setting_fields_are_visible_when_invoice_is_disabled(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'invoice_enable' => false,
            'receipt_enable' => true,
        ]);

        Livewire::test(InvoiceSetting::class)
            ->assertSee('Enable Receipt')
            ->assertSee('Show Submission Title on Receipt')
            ->assertSee('Receipt Notes');
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

    public function test_submission_payment_table_shows_latest_review_round_badge_in_submission_status_column(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            null,
            'Abstract Review',
        );

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            null,
            'Poster Review',
        );

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->firstOrFail();

        Livewire::test(SubmissionPaymentTable::class)
            ->assertTableColumnExists('submission_status')
            ->assertTableColumnStateSet(
                'submission_status',
                [SubmissionStatus::OnReview->value, 'Poster Review'],
                $payment,
            );
    }

    public function test_submission_payment_table_can_filter_by_submission_status(): void
    {
        $onReview = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
            userEmail: 'on-review@example.test',
        );

        $onPresentation = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::Presentation,
            submissionStatus: SubmissionStatus::OnPresentation,
            conference: $onReview['conference'],
            scheduledConference: $onReview['scheduledConference'],
            userEmail: 'on-presentation@example.test',
        );

        $this->queueSubmissionPayment($onReview['submission'], $onReview['paymentFee']);
        $this->queueSubmissionPayment($onPresentation['submission'], $onPresentation['paymentFee']);

        $onReviewPayment = $onReview['submission']->payment()->firstOrFail();
        $onPresentationPayment = $onPresentation['submission']->payment()->firstOrFail();

        Livewire::test(SubmissionPaymentTable::class)
            ->assertTableFilterExists('submission_status')
            ->assertCanSeeTableRecords([$onReviewPayment, $onPresentationPayment])
            ->filterTable('submission_status', SubmissionStatus::OnReview->value)
            ->assertCanSeeTableRecords([$onReviewPayment])
            ->assertCanNotSeeTableRecords([$onPresentationPayment])
            ->assertCountTableRecords(1);
    }

    public function test_submission_payment_table_only_shows_payments_for_valid_submission_statuses(): void
    {
        $valid = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
            userEmail: 'valid@example.test',
        );

        $declined = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::Declined,
            conference: $valid['conference'],
            scheduledConference: $valid['scheduledConference'],
            userEmail: 'declined-payment-list@example.test',
        );

        $withdrawn = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::Withdrawn,
            conference: $valid['conference'],
            scheduledConference: $valid['scheduledConference'],
            userEmail: 'withdrawn-payment-list@example.test',
        );

        $paymentDeclined = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::Payment,
            submissionStatus: SubmissionStatus::PaymentDeclined,
            conference: $valid['conference'],
            scheduledConference: $valid['scheduledConference'],
            userEmail: 'payment-declined-payment-list@example.test',
        );

        $this->queueSubmissionPayment($valid['submission'], $valid['paymentFee']);
        $this->queueSubmissionPayment($declined['submission'], $declined['paymentFee']);
        $this->queueSubmissionPayment($withdrawn['submission'], $withdrawn['paymentFee']);
        $this->queueSubmissionPayment($paymentDeclined['submission'], $paymentDeclined['paymentFee']);

        $validPayment = $valid['submission']->payment()->firstOrFail();
        $declinedPayment = $declined['submission']->payment()->firstOrFail();
        $withdrawnPayment = $withdrawn['submission']->payment()->firstOrFail();
        $paymentDeclinedPayment = $paymentDeclined['submission']->payment()->firstOrFail();

        Livewire::test(SubmissionPaymentTable::class)
            ->assertCanSeeTableRecords([$validPayment])
            ->assertCanNotSeeTableRecords([$declinedPayment, $withdrawnPayment, $paymentDeclinedPayment])
            ->assertCountTableRecords(1)
            ->assertTableFilterExists('submission_status', function (SelectFilter $filter): bool {
                $options = $filter->getOptions();

                return ! array_key_exists(SubmissionStatus::Declined->value, $options)
                    && ! array_key_exists(SubmissionStatus::Withdrawn->value, $options)
                    && ! array_key_exists(SubmissionStatus::PaymentDeclined->value, $options);
            });
    }

    public function test_dashboard_overview_submission_payment_count_only_includes_valid_submission_statuses(): void
    {
        $paidValid = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
            userEmail: 'paid-valid-overview@example.test',
        );

        $unpaidValid = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::Presentation,
            submissionStatus: SubmissionStatus::OnPresentation,
            conference: $paidValid['conference'],
            scheduledConference: $paidValid['scheduledConference'],
            userEmail: 'unpaid-valid-overview@example.test',
        );

        $paidDeclined = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::Declined,
            conference: $paidValid['conference'],
            scheduledConference: $paidValid['scheduledConference'],
            userEmail: 'paid-declined-overview@example.test',
        );

        $unpaidWithdrawn = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::Withdrawn,
            conference: $paidValid['conference'],
            scheduledConference: $paidValid['scheduledConference'],
            userEmail: 'unpaid-withdrawn-overview@example.test',
        );

        $paidPaymentDeclined = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::Payment,
            submissionStatus: SubmissionStatus::PaymentDeclined,
            conference: $paidValid['conference'],
            scheduledConference: $paidValid['scheduledConference'],
            userEmail: 'paid-payment-declined-overview@example.test',
        );

        $this->queueSubmissionPayment($paidValid['submission'], $paidValid['paymentFee']);
        $this->queueSubmissionPayment($unpaidValid['submission'], $unpaidValid['paymentFee']);
        $this->queueSubmissionPayment($paidDeclined['submission'], $paidDeclined['paymentFee']);
        $this->queueSubmissionPayment($unpaidWithdrawn['submission'], $unpaidWithdrawn['paymentFee']);
        $this->queueSubmissionPayment($paidPaymentDeclined['submission'], $paidPaymentDeclined['paymentFee']);

        $paidValid['submission']->payment()->firstOrFail()->update(['paid_at' => now()]);
        $paidDeclined['submission']->payment()->firstOrFail()->update(['paid_at' => now()]);
        $paidPaymentDeclined['submission']->payment()->firstOrFail()->update(['paid_at' => now()]);

        $this->assertSame('1 / 2', Overview::getSubmissionPaymentOverviewState());
    }

    public function test_submission_invoice_send_action_remains_available_after_invoice_was_sent(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'submission_payment_auto_notify' => false,
            'invoice_enable' => true,
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->with('scheduledConference')->firstOrFail();

        $this->assertTrue(SubmissionPaymentTable::canSendInvoiceFor($payment));

        $payment->markInvoiceAsSent();

        $this->assertTrue(SubmissionPaymentTable::canSendInvoiceFor($payment->refresh()->load('scheduledConference')));
    }

    public function test_participant_invoice_send_action_remains_available_after_invoice_was_sent(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setMeta('invoice_enable', true);

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
        )->load('scheduledConference');

        $this->assertTrue(ParticipantPaymentFeeTable::canSendInvoiceFor($payment));

        $payment->markInvoiceAsSent();

        $this->assertTrue(ParticipantPaymentFeeTable::canSendInvoiceFor($payment->refresh()->load('scheduledConference')));
    }

    public function test_payment_detail_can_send_submission_invoice(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'submission_payment_auto_notify' => false,
            'invoice_enable' => true,
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->with('scheduledConference')->firstOrFail();
        $editor = $this->makeConferenceManager($context, 'billing-editor@example.test');

        Notification::fake();

        $this->actingAs($editor);

        $this->assertTrue(PaymentDetail::canSendInvoiceFor($payment));
        $this->assertTrue($editor->can('update', $payment));

        $action = $this->getPaymentDetailAction($payment, 'send_invoice');

        $this->assertSame(__('general.send_invoice'), $action->getLabel());
        $this->assertTrue($action->isVisible());

        $action->call([
            'action' => $action,
            'record' => $payment,
        ]);

        Notification::assertSentTo($context['user'], SubmissionPayment::class);
        $this->assertNotNull($payment->refresh()->invoice);
        $this->assertTrue($payment->hasInvoiceBeenSent());
    }

    public function test_payment_detail_can_create_invoice_without_sending_notification(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setManyMeta([
            'submission_payment_auto_notify' => false,
            'invoice_enable' => true,
        ]);

        $this->queueSubmissionPayment($context['submission'], $context['paymentFee']);

        $payment = $context['submission']->payment()->with('scheduledConference')->firstOrFail();
        $editor = $this->makeConferenceManager($context, 'invoice-creator@example.test');

        Notification::fake();

        $this->actingAs($editor);

        $action = $this->getPaymentDetailAction($payment, 'create_invoice');

        $this->assertSame(__('general.create_invoice'), $action->getLabel());
        $this->assertTrue($action->isVisible());

        $action->call([
            'action' => $action,
            'record' => $payment,
        ]);

        Notification::assertNothingSent();
        $this->assertNotNull($payment->refresh()->invoice);
        $this->assertFalse($payment->hasInvoiceBeenSent());
        $this->assertFalse(
            $this->getPaymentDetailAction($payment->load('scheduledConference'), 'create_invoice')->isVisible()
        );
    }

    public function test_payment_detail_can_send_participant_invoice(): void
    {
        $context = $this->makeSubmissionContext(
            billingStage: SubmissionStage::PeerReview,
            submissionStage: SubmissionStage::PeerReview,
            submissionStatus: SubmissionStatus::OnReview,
        );

        $context['scheduledConference']->setMeta('invoice_enable', true);

        $participant = Participant::withoutGlobalScopes()->create([
            'given_name' => 'Participant',
            'family_name' => 'Tester',
            'email' => 'participant-detail@example.test',
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
        )->load('scheduledConference');
        $editor = $this->makeConferenceManager($context, 'participant-billing-editor@example.test');

        Notification::fake();

        $this->actingAs($editor);

        $this->assertTrue(PaymentDetail::canSendInvoiceFor($payment));
        $this->assertTrue($editor->can('update', $payment));

        $action = $this->getPaymentDetailAction($payment, 'send_invoice');

        $this->assertSame(__('general.send_invoice'), $action->getLabel());
        $this->assertTrue($action->isVisible());

        $action->call([
            'action' => $action,
            'record' => $payment,
        ]);

        Notification::assertSentTo($participant, ParticipantPayment::class);
        $this->assertNotNull($payment->refresh()->invoice);
        $this->assertTrue($payment->hasInvoiceBeenSent());
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

    protected function makeConferenceManager(array $context, string $email): User
    {
        foreach (['Payment:view', 'Payment:update'] as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ConferenceManager->value,
            'guard_name' => 'web',
            'conference_id' => $context['conference']->getKey(),
            'scheduled_conference_id' => 0,
        ]);

        $user = User::query()->create([
            'given_name' => 'Billing',
            'family_name' => 'Editor',
            'email' => $email,
            'password' => 'password123456',
        ]);

        $user->assignRole($role);

        return $user->refresh();
    }

    protected function getPaymentDetailAction(Payment $payment, string $name): \Filament\Actions\Action
    {
        $page = new class extends PaymentDetail
        {
            public function headerActions(): array
            {
                return $this->getHeaderActions();
            }
        };

        $page->record = $payment;

        foreach ($page->headerActions() as $headerAction) {
            if ($headerAction instanceof \Filament\Actions\Action && $headerAction->getName() === $name) {
                return $headerAction;
            }

            if (! $headerAction instanceof \Filament\Actions\ActionGroup) {
                continue;
            }

            $flatActions = $headerAction->getFlatActions();

            if (isset($flatActions[$name])) {
                return $flatActions[$name];
            }
        }

        $this->fail("Payment detail action [{$name}] was not found.");
    }

    protected function getInvoiceUrl(array $context, Payment $payment): string
    {
        return route('filament.scheduledConference.pages.invoice', [
            'conference' => $context['conference']->path,
            'serie' => $context['scheduledConference']->path,
            'record' => $payment,
        ]);
    }

    protected function getReceiptUrl(array $context, Payment $payment): string
    {
        return route('filament.scheduledConference.pages.receipt', [
            'conference' => $context['conference']->path,
            'serie' => $context['scheduledConference']->path,
            'record' => $payment,
        ]);
    }
}
