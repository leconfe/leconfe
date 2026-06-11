<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class NewSubmissionMail extends TemplateMailable
{
    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->scheduledConference->title,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Submission Author' => $submission->user->fullName,
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submission]),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'New Submission']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'New Submission: {{ Submission Title }} from {{ Conference Title }}.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p> A new article has been submitted and requires an editor assignment.</p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ Submission Title }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Author</td>
                    <td>:</td>
                    <td>{{ Submission Author }}</td>
                </tr>
            </table>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a></p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when a new submission is created.';
    }
}
