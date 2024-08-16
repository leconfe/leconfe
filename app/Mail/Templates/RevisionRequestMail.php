<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;

class RevisionRequestMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $title;

    public string $loginLink;

    public Log $log;

    public function __construct(protected Submission $submission)
    {
        $this->title = $submission->getMeta('title');
        $this->loginLink = route('livewirePageGroup.website.pages.login');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Revision Requested']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Revision Requested for {{ title }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when their submission is requested for revision';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ name }},</p>
            <p>This is an automated notification from the Leconfe System to inform you that your submission has been requested for revision.</p>
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
