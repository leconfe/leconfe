<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\SubmissionFile;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class NewRevisionUploadedMail extends TemplateMailable
{
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
        $this->log = Log::make(
            name: 'email',
            subject: $submissionFile->submission,
            description: __('general.email_sent', ['name' => 'New Revision Uploaded']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'New revision uploaded for {{ Submission Title }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to editors when a new revision is uploaded';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear Editors,</p>
            <p>A revision file has been uploaded for the submission "{{ Submission Title }}" in {{ Conference Title }}.</p>
            <table>
                <tr>
                    <td style="width:140px;">Submission ID</td>
                    <td>: {{ Submission ID }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Author</td>
                    <td>: {{ Submission Author }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Uploaded by</td>
                    <td>: {{ Uploaded By }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">File</td>
                    <td>: {{ File Name }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">File type</td>
                    <td>: {{ File Type }}</td>
                </tr>
                <tr>
                    <td style="width:140px;">Review round</td>
                    <td>: {{ Review Round Name }}</td>
                </tr>
            </table>
            <p>Please review the revision and continue the editorial workflow from the submission page.</p>
            <p>Click here to <a href="{{ Submission URL }}">View Submission</a>.</p>
        HTML;
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
