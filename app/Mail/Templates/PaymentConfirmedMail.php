<?php

namespace App\Mail\Templates;

use App\Managers\PaymentManager;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\PaymentDetail;

class PaymentConfirmedMail extends TemplateMailable
{
    public function __construct(Payment $payment)
    {
        $this->setAdditionalData([
            'Conference Title' => $payment->scheduledConference->title,
            'Payer Name' => $payment->user->full_name,
            'Payer Email' => $payment->email,
            'Payment Amount' => $payment->getFormattedFee(),
            'Payment Link' => PaymentDetail::getUrl(['record' => $payment]),
            'Payment Fee Name' => $payment->fee->name,
            'Payment ID' => $payment->getKey(),
        ]);
    }

    public static function getDefaultSubject(): string
    {
        return 'Payment Confirmed';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Payer Name }},</p>
            <p>Your payment with id {{ Payment ID }} has been successfully confirmed by the editor.</p>
            <p>Thank you for completing the payment. You may now proceed with the next steps process through your Leconfe account.</p>
            <p>If you have any questions, please feel free to contact the conference committee.</p>
            <p>Click here to <a href="{{ Payment Link }}">View Payment Detail</a></p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'New Participant Registered';
    }
}
