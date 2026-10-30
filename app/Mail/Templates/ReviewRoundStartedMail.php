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
        $this->setTranslatableData('Submission Title', $submission, 'title');
        $this->setTranslatableData('Conference Title', $submission->scheduledConference, 'title');

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => __('general.sent_for_a_new_round_of_reviews')]),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.review_round_started.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to authors when a new review round starts';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.review_round_started.body');
    }
}
