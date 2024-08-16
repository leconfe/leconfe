<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\SubmissionParticipant;

class ParticipantAssignedMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $submissionTitle;

    public string $name;

    public string $position;

    public Log $log;

    public function __construct(SubmissionParticipant $participant)
    {
        $this->submissionTitle = $participant->submission->getMeta('title');
        $this->name = $participant->user->fullName;
        $this->position = $participant->role->name;

        $this->log = Log::make(
            name: 'email',
            subject: $participant->submission,
            description: __('general.email_sent', ['name' => 'Participant Assigned'])
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'You have been assigned as a participant';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to participants when they are assigned to a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ name }},</p>
            <p>This is an automated notification from the Leconfe System to inform you that you have been assigned as a participant for the following submission:</p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ submissionTitle }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Position</td>
                    <td>:</td>
                    <td>{{ position }}</td>
                </tr>
            </table>
            <p>
                You can <a href="{{ loginLink }}">log in</a> to the system to see the details of the submission.
            </p>
        HTML;
    }
}
