<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;

class AcceptAbstractMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $title;

    public string $author;

    public string $loginLink;

    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->title = $submission->getMeta('title');
        $this->author = $submission->user->fullName;
        $this->loginLink = route('livewirePageGroup.website.pages.login');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Abstract Accepted']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Abstract Accepted';
    }

    public static function getDefaultDescription(): string
    {
        return 'This is an automated notification from System to inform you about a new submission.';
    }

    public static function getDefaultHtmlTemplate(): string
    {

        return <<<'HTML'
            <p> This is an automated notification from the Leconfe System to inform you about a new submission.</p>
            <p>
                Submission Details:
            </p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ title }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Author</td>
                    <td>:</td>
                    <td>{{ author }}</td>
                </tr>
            </table>
            <p>The submission is now available for your review and can be accessed through the System using your login credentials. Please <a href="{{ loginLink }}">log in</a> to the system to proceed with the evaluation process.</p>
        HTML;
    }
}
