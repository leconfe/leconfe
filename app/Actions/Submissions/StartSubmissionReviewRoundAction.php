<?php

namespace App\Actions\Submissions;

use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Models\Review;
use App\Models\Submission;
use App\Models\SubmissionReviewRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StartSubmissionReviewRoundAction
{
    use AsAction;

    public function handle(Submission $submission, array $defaultFileIds = [], ?User $triggeredBy = null): SubmissionReviewRound
    {
        $allowedFileIds = $submission->submissionFiles()
            ->whereIn('category', [SubmissionFileCategory::PAPER_FILES, SubmissionFileCategory::REVISION_FILES])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $defaultFileIds = $this->sanitizeDefaultFileIds($defaultFileIds)
            ->intersect($allowedFileIds)
            ->values()
            ->all();

        return DB::transaction(function () use ($submission, $defaultFileIds, $triggeredBy) {
            $openRoundIds = $submission->reviewRounds()
                ->open()
                ->pluck('id');

            if ($openRoundIds->isNotEmpty()) {
                $submission->reviewRounds()
                    ->whereIn('id', $openRoundIds)
                    ->update([
                        'status' => SubmissionReviewRound::STATUS_CLOSED,
                        'closed_at' => now(),
                        'updated_at' => now(),
                    ]);

                Review::query()
                    ->whereIn('review_round_id', $openRoundIds)
                    ->whereNull('date_completed')
                    ->whereIn('status', [ReviewerStatus::PENDING, ReviewerStatus::ACCEPTED])
                    ->update([
                        'status' => ReviewerStatus::CANCELED,
                        'updated_at' => now(),
                    ]);
            }

            $nextRoundNumber = ((int) $submission->reviewRounds()->max('round_number')) + 1;

            $reviewRound = $submission->reviewRounds()->create([
                'round_number' => $nextRoundNumber,
                'status' => SubmissionReviewRound::STATUS_OPEN,
                'triggered_by' => $triggeredBy?->getKey() ?? auth()->id(),
                'default_file_ids' => [],
                'opened_at' => now(),
                'closed_at' => null,
            ]);

            CloneSubmissionFilesToReviewRoundAction::run($submission, $reviewRound, $defaultFileIds);

            return $reviewRound->refresh();
        });
    }

    protected function sanitizeDefaultFileIds(array $defaultFileIds): \Illuminate\Support\Collection
    {
        return collect($defaultFileIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }
}
