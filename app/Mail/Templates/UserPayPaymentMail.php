<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Payment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;

class UserPayPaymentMail extends TemplateMailable
{
    public Log $log;

    public function __construct(Payment $payment)
    {
        $this->setAdditionalData([
            'Conference Title' => $payment->scheduledConference->title,
            'Payment Amount' => $payment->getFormattedFee(),
            'Payment Link' => PaymentDetail::getUrl(['record' => $payment]),
            'Payment Fee Name' => $payment->fee->name,
            'Payment ID' => $payment->getKey(),
            'Payer Name' => $payment->user->full_name,
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $payment,
            description: __('general.email_sent', ['name' => 'User Pay Payment']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Payment Proof Submitted by {{ Payer Name }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear Editor,</p>
            <p>We would like to inform you that {{ Payer Name }} has submitted a payment proof for the <b>{{ Conference Title }}</b></p>
            <p>You can review the payment details at the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'User Pay Payment';
    }
}
