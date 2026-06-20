<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Submission;
use App\Models\SubmissionReviewRound;
use App\Panel\ScheduledConference\Resources\SubmissionResource;

class ReviewRoundStartedMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(protected Submission $submission, protected SubmissionReviewRound $reviewRound)
    {
        $reviewRoundName = filled($reviewRound->name)
            ? $reviewRound->name
            : 'Round '.$reviewRound->round_number;

        $this->setAdditionalData([
            'Conference Title' => $submission->scheduledConference->title,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Submission Author' => $submission->user->fullName,
            'Review Round Number' => $reviewRound->round_number,
            'Review Round Name' => $reviewRoundName,
            'Submission URL' => SubmissionResource::getUrl('view', ['record' => $submission]),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => __('general.sent_for_a_new_round_of_reviews')]),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Your submission {{ Submission Title }} has been sent for another review round';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when a new review round starts';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>Your submission "{{ Submission Title }}" has been sent for {{ Review Round Name }}.</p>
            <p>The editorial team will notify you when this review round progresses.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a>.</p>
        HTML;
    }
}
