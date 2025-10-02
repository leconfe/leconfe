<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\SubmissionFile;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

/**
 * If the author has already uploaded a new paper, please notify the editor.
 */
class NewPaperUploadedMail extends TemplateMailable
{
    public string $submissionTitle;

    public string $uploader;

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
            description: __('general.email_sent', ['name' => 'New Paper Uploaded']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'New Paper Uploaded';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a new paper is uploaded';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>There's a new paper uploaded on {{ Submission Title }}, <a href="{{ Submission URL }}">click here</a> to access the submission.</p>
        HTML;
    }
}
