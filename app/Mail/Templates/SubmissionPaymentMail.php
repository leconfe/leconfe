<?php

namespace App\Mail\Templates;

use App\Models\Payment;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\PaymentDetail;

class SubmissionPaymentMail extends TemplateMailable
{
    public function __construct(Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->payment->scheduledConference->title,
            'Submission Author' => $submission->user->full_name,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Payment Amount' => $submission->payment->getFormattedFee(),
            'Payment Link' => PaymentDetail::getUrl(['record' => $submission->payment])
        ]);
    }

    public static function getDefaultSubject(): string
    {
        return 'Submission Payment for: {{ Submission Title }} on {{ Conference Title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We would like to inform you that a payment requirement has been added to your submission for {{ Conference Title }}.</p>
            <p>Submission Details:</p>
            <ul>
                <li>Title : <b>{{ Submission Title }}</b></li>
                <li>Reference ID : <b>{{ Submission ID }}</b></li>
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
