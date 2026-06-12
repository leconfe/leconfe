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

        $this->log = Log::make(
            name: 'email',
            subject: $participant,
            description: __('general.email_sent', ['name' => 'Participant Registered']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'New Participant Registered for {{ Conference Title }}.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>A new participant has been registered.</p>
            <table>
                <tr>
                    <td style="">Name</td>
                    <td>:</td>
                    <td>{{ Participant Name }}</td>
                </tr>
                <tr>
                    <td style="">Email</td>
                    <td>:</td>
                    <td>{{ Participant Email }}</td>
                </tr>
                <tr>
                    <td style="">Payment Fee Name</td>
                    <td>:</td>
                    <td>{{ Payment Fee Name }}</td>
                </tr>
                <tr>
                    <td style="">Payment Amount</td>
                    <td>:</td>
                    <td>{{ Payment Amount }}</td>
                </tr>
            </table>
            <p>Click here to <a href="{{ Payment Link }}">View Participant Registration</a></p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'New Participant Registered';
    }
}
