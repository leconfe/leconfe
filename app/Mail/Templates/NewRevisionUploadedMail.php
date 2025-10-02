<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\SubmissionFile;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class NewRevisionUploadedMail extends TemplateMailable
{
    public Log $log;

    public function __construct(SubmissionFile $submissionFile)
    {
        $this->setAdditionalData([
            'Submission Title' => $submissionFile->submission->getMeta('title'),
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submissionFile->submission]),
        ]);
        $this->log = Log::make(
            name: 'email',
            subject: $submissionFile->submission,
            description: __('general.email_sent', ['name' => 'New Revision Uploaded']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'New Revision Uploaded';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a new revision is uploaded';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>There's a new revision uploaded on {{ Submission Title }}, <a href="{{ Submission URL }}">click here</a> to access the submission.</p>
        HTML;
    }
}
