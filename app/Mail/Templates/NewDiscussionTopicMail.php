<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\DiscussionTopic;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class NewDiscussionTopicMail extends TemplateMailable
{
    public string $topicName;

    public string $submissionTitle;

    public string $linkLogin;

    public Log $log;

    public function __construct(DiscussionTopic $discussionTopic)
    {
        $this->setAdditionalData([
            'Submission Title' => $discussionTopic->submission->getMeta('title'),
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $discussionTopic->submission]),
            'Topic Name' =>  $discussionTopic->name,
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $discussionTopic->submission,
            description: __('general.email_sent', ['name' => 'New Discussion Topic']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'New discussion topic';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent when a new discussion topic is created.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
         <p> You've been added to a new discussion. Here are the details:</p>
         <table>
                <tr>
                    <td style="width:100px;">Submission</td>
                    <td>: {{ Submission Title }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Topic</td>
                    <td>: {{ Topic Name }}</td>
                </tr>
            </table>
            <p><a href="{{ Submission URL }}" target="_blank">Click here</a> to access the discussion.</p>
        HTML;
    }
}
