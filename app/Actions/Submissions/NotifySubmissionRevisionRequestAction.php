<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Mail\Templates\RevisionRequestMail;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;

class NotifySubmissionRevisionRequestAction
{
    use AsAction;

    public function handle(
        Submission $submission,
        string $subject,
        string $message,
        bool $notifyAuthor = true,
        ?User $actor = null,
    ): void {
        $actor ??= auth()->user();

        Log::make(
            name: 'submission',
            subject: $submission,
            description: __('general.submission_request_revision', [
                'name' => $actor?->full_name,
            ]),
            event: 'submission-request-revision-notification',
        )
            ->by($actor)
            ->save();

        if (! $notifyAuthor) {
            return;
        }

        Mail::to($submission->user->email)
            ->send(
                (new RevisionRequestMail($submission))
                    ->subjectUsing($subject)
                    ->contentUsing($message)
            );
    }
}
