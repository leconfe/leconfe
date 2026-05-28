<?php

namespace Tests\Unit;

use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use Tests\TestCase;

class PaymentDetailTest extends TestCase
{
    public function test_missing_notification_checkbox_defaults_to_sending_for_participant_payment(): void
    {
        $page = new class extends PaymentDetail
        {
            public function shouldSendParticipantPaymentNotificationPublic(Payment $record, array $data): bool
            {
                return $this->shouldSendParticipantPaymentNotification($record, $data);
            }
        };

        $payment = new Payment([
            'type' => PaymentManager::TYPE_PARTICIPANT_FEE,
        ]);

        $this->assertTrue($page->shouldSendParticipantPaymentNotificationPublic($payment, []));
    }

    public function test_submission_payment_never_sends_participant_payment_notification(): void
    {
        $page = new class extends PaymentDetail
        {
            public function shouldSendParticipantPaymentNotificationPublic(Payment $record, array $data): bool
            {
                return $this->shouldSendParticipantPaymentNotification($record, $data);
            }
        };

        $payment = new Payment([
            'type' => PaymentManager::TYPE_SUBMISSION_FEE,
        ]);

        $this->assertFalse($page->shouldSendParticipantPaymentNotificationPublic($payment, []));
    }
}
