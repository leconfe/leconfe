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
        $this->setTranslatableData('Announcement Title', $announcement, 'title');
        $this->setTranslatableData('Announcement Summary', $announcement, 'summary');
        $this->log = Log::make(
            name: 'email',
            subject: $announcement,
            description: __('general.email_sent', ['name' => 'New Announcement']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.new_announcement.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.new_announcement.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when a new announcement is created.';
    }
}
