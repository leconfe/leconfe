<?php

namespace App\Actions\SubmissionFiles;

use App\Models\Media;
use App\Models\Submission;
use App\Models\SubmissionFileType;
use Lorisleiva\Actions\Concerns\AsAction;

class UploadSubmissionFileAction
{
    use AsAction;

    public function handle(Submission $submission, Media $file, string $category, SubmissionFileType $type, ?int $reviewRoundId = null)
    {
        return $submission->submissionFiles()->updateOrCreate([
            'media_id' => $file->getKey(),
        ], [
            'media_id' => $file->getKey(),
            'review_round_id' => $reviewRoundId,
            'submission_file_type_id' => $type->getKey(),
            'category' => $category,
        ]);
    }
}
