<?php

namespace App\Actions\SubmissionFiles;

use App\Models\SubmissionFileType;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmissionFileTypeCreateAction
{
    use AsAction;

    public function handle($data): SubmissionFileType
    {
        try {
            DB::beginTransaction();

            $submissionFileType = SubmissionFileType::create($data);

            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                $submissionFileType->setManyMeta($data['meta']);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $submissionFileType;
    }
}