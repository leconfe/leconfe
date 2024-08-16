<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Review;

class ReviewerCancelationMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $name;

    public string $submissionTitle;

    public Log $log;

    public function __construct(Review $review)
    {
        $this->name = $review->user->fullName;
        $this->submissionTitle = $review->submission->getMeta('title');

        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Canceled Invitation']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'You have been cancelled as a reviewer';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to reviewers when they are cancelled from a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ name }},</p>
            <p>This is an automated notification from the Leconfe System to inform you that you have been cancelled as a reviewer for the following submission:</p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ submissionTitle }}</td>
                </tr>
            </table>
            <p>
                Thank you for your interest in reviewing for the Leconfe System. We hope that you will consider reviewing for us again in the future.
            </p>
        HTML;
    }
}
