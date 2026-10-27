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
        $this->setTranslatableData('Payment Fee Name', $payment->fee, 'name');
        $this->setTranslatableData('Conference Title', $payment->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $payment,
            description: __('general.email_sent', ['name' => 'User Pay Payment']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.user_pay_payment.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.user_pay_payment.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'User Pay Payment';
    }
}
