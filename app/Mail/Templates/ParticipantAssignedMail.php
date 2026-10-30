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
        $this->submissionTitle = (string) $participant->submission->translated('title');
        $this->name = $participant->user->fullName;
        $this->position = $participant->role->display_name;

        $this->setAdditionalData([
            'Participant Name' => $participant->user->fullName,
            'Participant Role' => $participant->role->display_name,
            'Conference Title' => $participant->submission->scheduledConference->title,
            'Submission Title' => $participant->submission->getMeta('title'),
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $participant->submission]),
        ]);
        $this->setTranslatableData('Submission Title', $participant->submission, 'title');
        $this->setTranslatableData('Conference Title', $participant->submission->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $participant->submission,
            description: __('general.email_sent', ['name' => 'Participant Assigned'])
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.participant_assigned.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to participants when they are assigned to a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.participant_assigned.body');
    }
}
