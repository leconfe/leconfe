<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\SubmissionFile;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class NewReviewFileUploadedMail extends TemplateMailable
{
    public string $submissionTitle;

    public string $uploader;

    public Log $log;

    public function __construct(SubmissionFile $submissionFile)
    {
        $this->setAdditionalData([
            'Conference Title' => $submissionFile->submission->scheduledConference->title,
            'Submission Title' => $submissionFile->submission->getMeta('title'),
            'Submission ID' => $submissionFile->submission->getKey(),
            'Submission Author' => $submissionFile->submission->user->fullName,
            'Uploaded By' => $submissionFile->user?->fullName ?? 'Unknown user',
            'File Name' => $this->resolveFileName($submissionFile),
            'File Type' => $submissionFile->type?->name ?? 'Unspecified',
            'Review Round Name' => $this->resolveReviewRoundName($submissionFile),
            'Submission URL' => $this->resolveSubmissionUrl($submissionFile),
        ]);
        $this->setTranslatableData('Submission Title', $submissionFile->submission, 'title');
        $this->setTranslatableData('Conference Title', $submissionFile->submission->scheduledConference, 'title');
        $this->setTranslatableData('File Type', $submissionFile->type, 'name');

        $this->log = Log::make(
            name: 'email',
            subject: $submissionFile->submission,
            description: __('general.email_sent', ['name' => __('general.review_file_uploaded')]),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.new_review_file_uploaded.subject');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a new review file is uploaded';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.new_review_file_uploaded.body');
    }

    protected function resolveFileName(SubmissionFile $submissionFile): string
    {
        return $submissionFile->media?->original_file_name
            ?? $submissionFile->media?->file_name
            ?? 'File #'.$submissionFile->getKey();
    }

    protected function resolveReviewRoundName(SubmissionFile $submissionFile): string
    {
        $reviewRound = $submissionFile->reviewRound;

        if (! $reviewRound) {
            return 'Not assigned';
        }

        return filled($reviewRound->name)
            ? $reviewRound->name
            : 'Round '.$reviewRound->round_number;
    }

    protected function resolveSubmissionUrl(SubmissionFile $submissionFile): string
    {
        try {
            return SubmissionResource::getUrl('view', [
                'record' => $submissionFile->submission,
                'tenant' => $submissionFile->submission->conference,
            ]);
        } catch (RouteNotFoundException|UrlGenerationException) {
            return url('/');
        }
    }
}
