<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;

class DeclinePaperMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $title;

    public string $authorName;

    public string $loginLink;

    public Log $log;

    public function __construct(protected Submission $submission)
    {
        $this->title = $submission->getMeta('title');
        $this->authorName = $submission->user->fullName;
        $this->loginLink = route('livewirePageGroup.website.pages.login');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Paper Declined']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Paper Declined: {{ title }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is declined';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ authorName }},</p>
            <p>This is an automated notification from the Leconfe System to inform you that your submission paper has been declined.</p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ title }}</td>
                </tr>
            </table>
            <p>
                Please <a href="{{ loginLink }}"> log in</a> to the system to proceed with the revision process.
            </p>
        HTML;
    }
}
