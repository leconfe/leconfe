<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Announcement;

class NewAnnouncementMail extends TemplateMailable
{
    public string $title;

    public string $content;

    public string $announcementUrl;

    public Log $log;

    /**
     * Create a new message instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->title = $announcement->title;
        $this->content = $announcement->getMeta('content');
        $this->announcementUrl = $announcement->getUrl();

        $this->log = Log::make(
            name: 'email',
            subject: $announcement,
            description: __('general.email_sent', ['name' => 'New Announcement']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return '{{ title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
        {{{  content  }}}
        <p>Click <a href="{{ announcementUrl }}">here</a> to read the full announcement.</p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when a new announcement is created.';
    }
}
