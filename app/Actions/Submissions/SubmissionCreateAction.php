<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Models\Submission;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmissionCreateAction
{
    use AsAction;

    public function handle(array $data): Submission
    {
        try {
            DB::beginTransaction();

            $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
            unset($data['meta']);

            $submission = Submission::create($data);

            // The paper language decides which title is primary, so it is saved first.
            $meta['language'] = filled($meta['language'] ?? null) ? $meta['language'] : app(ContentLocales::class)->default();
            $submission->setMeta('language', $meta['language']);

            $meta = $submission->syncTranslatableState(['meta' => $meta])['meta'];

            $submission->setManyMeta($meta);
            $submission->persistTranslatableState();

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
