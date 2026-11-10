<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSubmissionPublicationDateAction
{
    use AsAction;

    public function handle(Submission $submission, ?string $publishedAt): Submission
    {
        return DB::transaction(function () use ($submission, $publishedAt) {
            $submission = Submission::query()->lockForUpdate()->findOrFail($submission->getKey());

            Gate::authorize('updatePublicationDate', $submission);

            $data = Validator::make(
                ['published_at' => $publishedAt],
                ['published_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']],
                [],
                ['published_at' => __('general.date_published')],
            )->validate();

            $previousDate = $submission->published_at?->toDateString();

            if ($previousDate === $data['published_at']) {
                return $submission;
            }

            $submission->update(['published_at' => $data['published_at']]);

            Log::make(
                name: 'submission',
                subject: $submission,
                description: __('general.submission_publication_date_updated'),
            )
                ->properties([
                    'old' => ['published_at' => $previousDate],
                    'attributes' => ['published_at' => $data['published_at']],
                ])
                ->by(auth()->user())
                ->save();

            return $submission;
        });
    }
}
