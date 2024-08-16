<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\Submission;

class ThankAuthorMail extends TemplateMailable
{
    public string $author;

    public string $title;

    public string $conferenceName;

    public string $loginLink;

    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->title = $submission->getMeta('title');
        $this->author = $submission->user->fullName;
        $this->conferenceName = $submission->conference->name;
        $this->loginLink = route('livewirePageGroup.website.pages.login');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Thank Author']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Thank you for your submission to {{ conferenceName }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email template is sent when a new submission is created.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ author }},</p>
            <p>Thank you for your recent submission to the Leconfe System for the {{ conferenceName }}. We appreciate your interest in participating in our conference.</p>

            <p>Submission Details:</p>

            <table>
                <tr>
                    <td style="width: 100px">Title</td>
                    <td>:</td>
                    <td>{{ title }}</td>
                </tr>
            </table>

            <p>We have received your submission and it is currently being reviewed by our team. You will be notified of the outcome of the review process in due course.</p>

            <p>If you have any questions or need further information, please do not hesitate to contact us.</p>
        HTML;
    }
}
