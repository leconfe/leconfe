<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Participant;
use App\Panel\ScheduledConference\Pages\PaymentDetail;

class ParticipantRegisteredMail extends TemplateMailable
{
    public Log $log;

    public function __construct(Participant $participant)
    {
        $this->setAdditionalData([
            'Conference Title' => $participant->payment->scheduledConference->title,
            'Participant Name' => $participant->full_name,
            'Participant Email' => $participant->email,
            'Payment Amount' => $participant->payment->getFormattedFee(),
            'Payment Link' => PaymentDetail::getUrl(['record' => $participant->payment]),
            'Payment Fee Name' => $participant->payment->fee->name,
        ]);
        $this->setTranslatableData('Payment Fee Name', $participant->payment->fee, 'name');
        $this->setTranslatableData('Conference Title', $participant->payment->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $participant,
            description: __('general.email_sent', ['name' => 'Participant Registered']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.participant_registered.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.participant_registered.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'New Participant Registered';
    }
}
