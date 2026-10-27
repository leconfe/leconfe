<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;

class SubmissionPaymentMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->payment->scheduledConference->title,
            'Submission Author' => $submission->user->full_name,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Payment Amount' => $submission->payment->getFormattedFee(),
            'Payment Link' => $submission->payment->getPaymentDetailUrl(),
        ]);
        $this->setTranslatableData('Submission Title', $submission, 'title');
        $this->setTranslatableData('Conference Title', $submission->payment->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Submission Payment']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.submission_payment.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.submission_payment.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'Submission Payment email template';
    }
}
