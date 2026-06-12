<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\SubmissionParticipant;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

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

        $this->setAdditionalData([
            'Participant Name' => $participant->user->fullName,
            'Participant Role' => $participant->role->name,
            'Conference Title' => $participant->submission->scheduledConference->title,
            'Submission Title' => $participant->submission->getMeta('title'),
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $participant->submission]),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $participant->submission,
            description: __('general.email_sent', ['name' => 'Participant Assigned'])
        )->by(auth()->user());
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
            <p>Dear {{ Participant Name }},</p>
            <p>You have been assigned as {{ Participant Role }} for "{{ Submission Title }}" on {{ Conference Title }}.</p>
            <p>Please guide the submission through the editorial process.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
        HTML;
    }
}
