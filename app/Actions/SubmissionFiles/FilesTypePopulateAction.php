<?php

namespace App\Actions\SubmissionFiles;

use App\Models\ScheduledConference;
use App\Models\SubmissionFileType;
use Lorisleiva\Actions\Concerns\AsAction;

class FilesTypePopulateAction
{
    use AsAction;

    public function handle(ScheduledConference $scheduledConference)
    {
        foreach ([
            'abstract' => 'Abstract',
            'full_paper' => 'Full Paper',
            'poster' => 'Poster',
            'other' => 'Other',
        ] as $code => $name) {
            if (SubmissionFileType::where('scheduled_conference_id', $scheduledConference->id)
                ->where('builtin_code', $code)->exists()) {
                continue;
            }

            $type = SubmissionFileType::firstOrCreate([
                'name' => $name,
                'scheduled_conference_id' => $scheduledConference->id,
            ]);

            if ($type->wasRecentlyCreated) {
                $type->forceFill(['builtin_code' => $code])->save();
            }
        }
    }
}
