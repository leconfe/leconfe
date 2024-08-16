<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Review;

class ReviewerInvitationMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public string $name;

    public string $submissionTitle;

    public string $responseDueDate;

    public string $reviewDueDate;

    public string $loginLink;

    public array $logDetail;

    public Log $log;

    public function __construct(Review $review)
    {
        $submission = $review->submission;
        $scheduledConference = $submission->scheduledConference;

        $this->name = $review->user->fullName;
        $this->submissionTitle = $review->submission->getMeta('title');

        $this->responseDueDate = now()->addDays($scheduledConference->getMeta('review_invitation_response_deadline') ?? 28)->format('d F Y');

        $this->reviewDueDate = now()->addDays($scheduledConference->getMeta('review_completion_deadline') ?? 28)->format('d F Y');

        $this->loginLink = route('filament.scheduledConference.pages.dashboard', ['scheduledConference' => $scheduledConference, 'conference' => $scheduledConference->conference]);

        $this->log = Log::make(
            name: 'email',
            subject: $review->submission,
            description: __('general.email_sent', ['name' => 'Reviewer Invitation']),
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'You have been assigned as a reviewer';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to reviewers when they are assigned to a submission';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ name }},</p>
            <p>This is an automated notification from the Leconfe System to inform you that you have been assigned as a reviewer for the following submission:</p>
            <table>
                <tr>
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ submissionTitle }}</td>
                </tr>
            </table>
            And here is the review details:
            <table>
                <tr>
                    <td>Response Due Date</td>
                    <td>:</td>
                    <td>{{ responseDueDate }}</td>
                </tr>
                <tr>
                    <td>Review Due Date</td>
                    <td>:</td>
                    <td>{{ reviewDueDate }}</td>
                </tr>
            </table>
            <p>Please <a href="{{ loginLink }}"> log in</a> to the system to proceed with the evaluation process.</p>
        HTML;
    }
}
