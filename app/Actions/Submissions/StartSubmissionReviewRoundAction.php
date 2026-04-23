<?php

namespace App\Actions\Submissions;

use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Models\Review;
use App\Models\SubmissionFile;
use App\Models\Submission;
use App\Models\SubmissionReviewRound;
use App\Models\User;
use Illuminate\Support\Collection;
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

            $newDefaultFileIds = $this->cloneFilesToReviewRound($submission, $reviewRound, $defaultFileIds);

            $reviewRound->update([
                'default_file_ids' => $newDefaultFileIds,
            ]);

            return $reviewRound->refresh();
        });
    }

    protected function cloneFilesToReviewRound(Submission $submission, SubmissionReviewRound $reviewRound, array $defaultFileIds): array
    {
        if (empty($defaultFileIds)) {
            return [];
        }

        $sourceFiles = $submission->submissionFiles()
            ->with(['media', 'type'])
            ->whereIn('id', $defaultFileIds)
            ->get()
            ->keyBy('id');

        $newFileIds = [];

        foreach ($defaultFileIds as $fileId) {
            $sourceFile = $sourceFiles->get($fileId);

            if (! $sourceFile || ! $sourceFile->media || ! $sourceFile->type) {
                continue;
            }

            $clonedMedia = $sourceFile->media->copy(
                $submission,
                $sourceFile->category,
                'private-files'
            );

            $clonedFile = SubmissionFile::withoutEvents(function () use ($submission, $reviewRound, $sourceFile, $clonedMedia) {
                return SubmissionFile::query()->create([
                    'submission_id' => $submission->getKey(),
                    'review_round_id' => $reviewRound->getKey(),
                    'submission_file_type_id' => $sourceFile->type->getKey(),
                    'media_id' => $clonedMedia->getKey(),
                    'user_id' => $reviewRound->triggered_by ?? auth()->id() ?? $submission->user_id,
                    'category' => $sourceFile->category,
                ]);
            });

            $newFileIds[] = $clonedFile->getKey();
        }

        return $newFileIds;
    }

    protected function sanitizeDefaultFileIds(array $defaultFileIds): Collection
    {
        return collect($defaultFileIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }
}
