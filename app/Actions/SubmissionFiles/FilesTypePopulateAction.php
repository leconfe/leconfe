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
            'Abstract',
            'Full Paper',
            'Poster',
            'Other',
        ] as $name) {
            SubmissionFileType::firstOrCreate([
                'name' => $name,
                'scheduled_conference_id' => $scheduledConference->id,
            ]);
        }
    }
}
