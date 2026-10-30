<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\DiscussionTopic;

class NewDiscussionTopicMail extends TemplateMailable
{
    public string $topicName;

    public string $submissionTitle;

    public string $linkLogin;

    public Log $log;

    public function __construct(DiscussionTopic $discussionTopic, string $submissionUrl)
    {
        $this->setAdditionalData([
            'Submission Title' => $discussionTopic->submission->getMeta('title'),
            'Submission URL' => $submissionUrl,
            'Topic Name' => $discussionTopic->name,
        ]);
        $this->setTranslatableData('Submission Title', $discussionTopic->submission, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $discussionTopic->submission,
            description: __('general.email_sent', ['name' => 'New Discussion Topic']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.new_discussion_topic.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent when a new discussion topic is created.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.new_discussion_topic.body');
    }
}
