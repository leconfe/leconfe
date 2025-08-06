<?php

namespace App\Actions\SubmissionFiles;

use App\Models\SubmissionFileType;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmissionFileTypeUpdateAction
{
    use AsAction;

    public function handle(SubmissionFileType $submissionFileType, $data): SubmissionFileType
    {
        try {
            DB::beginTransaction();

            $submissionFileType->update($data);

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