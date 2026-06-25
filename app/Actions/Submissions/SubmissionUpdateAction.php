<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\Billing\SubmissionBillingNotifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as Logger;
use Illuminate\Validation\ValidationException;
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
            $topicField = $this->getTopicField($data);
            $topicIds = [];

            if (($data['revision_required'] ?? null) === false && ! array_key_exists('revision_due_at', $data)) {
                $data['revision_due_at'] = null;
            }

            if ($topicField !== null) {
                $topicIds = collect(Arr::wrap($data[$topicField]))
                    ->filter(fn ($topicId) => filled($topicId))
                    ->unique()
                    ->values()
                    ->all();

                $topicLimit = $submission
                    ->scheduledConference()
                    ->first()
                    ?->getSubmissionTopicSelectionLimit();

                if ($topicLimit !== null && count($topicIds) > $topicLimit) {
                    throw ValidationException::withMessages([
                        $topicField => [__('validation.max.array', [
                            'attribute' => __('general.topic'),
                            'max' => $topicLimit,
                        ])],
                    ]);
                }

                unset($data[$topicField]);
            }

            $submission->update($data);

            if ($topicField !== null) {
                $submission->topics()->sync($topicIds);
            }

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

            if (
                $submission->stage === SubmissionStage::PeerReview &&
                in_array($submission->status, [SubmissionStatus::OnReview, SubmissionStatus::OnPayment], true) &&
                ! $submission->reviewRounds()->exists()
            ) {
                StartSubmissionReviewRoundAction::run($submission, [], auth()->user());
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

    private function getTopicField(array $data): ?string
    {
        foreach (['topics', 'topic'] as $field) {
            if (array_key_exists($field, $data)) {
                return $field;
            }
        }

        return null;
    }
}
