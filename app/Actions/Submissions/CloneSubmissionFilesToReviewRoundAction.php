<?php

namespace App\Actions\Submissions;

use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionReviewRound;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneSubmissionFilesToReviewRoundAction
{
    use AsAction;

    public function handle(Submission $submission, SubmissionReviewRound $reviewRound, array $sourceFileIds = []): array
    {
        $sourceFileIds = $this->sanitizeFileIds($sourceFileIds);

        if ($sourceFileIds->isEmpty()) {
            return [];
        }

        $sourceFiles = $submission->submissionFiles()
            ->with(['media', 'type'])
            ->whereIn('id', $sourceFileIds)
            ->get()
            ->keyBy('id');

        $clonedFileIds = [];

        foreach ($sourceFileIds as $sourceFileId) {
            $sourceFile = $sourceFiles->get($sourceFileId);

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

            $clonedFileIds[] = $clonedFile->getKey();
        }

        if ($clonedFileIds !== []) {
            $reviewRound->update([
                'default_file_ids' => collect($reviewRound->default_file_ids ?? [])
                    ->merge($clonedFileIds)
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }

        return $clonedFileIds;
    }

    protected function sanitizeFileIds(array $fileIds): Collection
    {
        return collect($fileIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }
}
