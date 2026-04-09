<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Models\Submission;
use App\Services\Billing\SubmissionBillingNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as Logger;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmissionUpdateAction
{
    use AsAction;

    public function handle(array $data, Submission $submission): Submission
    {
        try {
            DB::beginTransaction();

            $shouldEvaluateSubmissionBilling = array_key_exists('stage', $data);
            $submissionId = $submission->getKey();

            $submission->update($data);

            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                $submission->setManyMeta($data['meta']);
            }

            if ($shouldEvaluateSubmissionBilling) {
                DB::afterCommit(function () use ($submissionId) {
                    $freshSubmission = Submission::withoutGlobalScopes()
                        ->with(['payment', 'user', 'scheduledConference'])
                        ->find($submissionId);

                    if (! $freshSubmission) {
                        return;
                    }

                    try {
                        app(SubmissionBillingNotifier::class)->maybeNotifyForSubmission($freshSubmission);
                    } catch (\Throwable $th) {
                        Logger::error($th->getMessage());
                    }
                });
            }

            Log::make(
                name: 'submission',
                subject: $submission,
                description: __('general.submission_metadata_updated'),
            )
                ->by(auth()?->user())
                ->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $submission;
    }
}
