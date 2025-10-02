<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Announcement;

class NewAnnouncementMail extends TemplateMailable
{
    public Log $log;

    /**
     * Create a new message instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->setAdditionalData([
            'Announcement Title' => $announcement->title,
            'Announcement Summary' => $announcement->getMeta('summary'),
            'Announcement URL' => $announcement->getUrl(),
        ]);
        $this->log = Log::make(
            name: 'email',
            subject: $announcement,
            description: __('general.email_sent', ['name' => 'New Announcement']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return '{{ Announcement Title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
        {{{  Announcement Summary  }}}
        <p>Click <a href="{{ Announcement URL }}">here</a> to read the full announcement.</p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when a new announcement is created.';
    }
}
