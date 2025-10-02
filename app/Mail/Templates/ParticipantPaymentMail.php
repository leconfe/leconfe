<?php

namespace App\Mail\Templates;

use App\Models\Participant;
use App\Models\Payment;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\PaymentDetail;

class ParticipantPaymentMail extends TemplateMailable
{
    public function __construct(Participant $participant)
    {
        $this->setAdditionalData([
            'Conference Title' => $participant->payment->scheduledConference->title,
            'Participant Name' => $participant->full_name,
            'Payment Amount' => $participant->payment->getFormattedFee(),
            'Payment Link' => PaymentDetail::getUrl(['record' => $participant->payment]),
            'Payment Fee Name' => $participant->payment->fee->name,
        ]);
    }

    public static function getDefaultSubject(): string
    {
        return 'Participant Payment for: {{ Payment Fee Name }} on {{ Conference Title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Participant Name }},</p>
            <p>We would like to inform you that a payment requirement has been added to your participant registration for {{ Conference Title }}.</p>
            <p>Participant Payment Details:</p>
            <ul>
                <li>Registration Type : <b>{{ Payment Fee Name }}</b></li>
                <li>Amount Due : <b>{{ Payment Amount }}</b></li>
            </ul>
            <p>To proceed, please complete your payment by visiting the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            <p>
                If you have already made the payment, kindly disregard this notice.
            </p>
            <p>Thank you for your participation, and we look forward to your contribution to {{ Conference Title }}.</p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'Submission Payment email template';
    }
}
