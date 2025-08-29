<?php

namespace App\Mail\Templates;

use App\Managers\PaymentManager;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\PaymentDetail;

class UserPayPaymentMail extends TemplateMailable
{
    public function __construct(Payment $payment)
    {
        $this->setAdditionalData([
            'Conference Title' => $payment->scheduledConference->title,
            'Full Name' => $payment->type == PaymentManager::TYPE_SUBMISSION_FEE ? $payment->user->full_name : $payment->model->full_name,
            'Payment Amount' => $payment->getFormattedFee(),
            'Payment Link' => PaymentDetail::getUrl(['record' => $payment]),
            'Payment Fee Name' => $payment->fee->name,
        ]);
    }

    public static function getDefaultSubject(): string
    {
        return 'Payment proof for {{ Payment Fee Name }} on {{ Conference Title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear Editor,</p>
            <p>We would like to inform you that there's a payment proof for <b>{{ Payment Fee Name }}</b> at the <b>{{ Conference Title }}</b></p>
            <p>Open payment information by visiting the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'User Pay Payment';
    }
}
