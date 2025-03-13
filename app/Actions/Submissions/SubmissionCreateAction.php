<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmissionCreateAction
{
    use AsAction;

    public function handle(array $data): Submission
    {
        try {
            DB::beginTransaction();

            $submission = Submission::create($data);

            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                $submission->setManyMeta($data['meta']);
            }

            Log::make(
                name: 'submission',
                subject: $submission,
                description: __('general.submission_created')
            )
                ->by(auth()->user())
                ->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $submission;
    }
}
