<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Participant;

class ParticipantPaymentMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(Participant $participant)
    {
        $this->setAdditionalData([
            'Conference Title' => $participant->payment->scheduledConference->title,
            'Participant Name' => $participant->full_name,
            'Payment Amount' => $participant->payment->getFormattedFee(),
            'Payment Link' => $participant->payment->getPaymentDetailUrl(),
            'Payment Fee Name' => $participant->payment->fee->name,
        ]);
        $this->setTranslatableData('Payment Fee Name', $participant->payment->fee, 'name');
        $this->setTranslatableData('Conference Title', $participant->payment->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $participant,
            description: __('general.email_sent', ['name' => 'Participant Payment']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.participant_payment.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.participant_payment.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'Submission Payment email template';
    }
}
