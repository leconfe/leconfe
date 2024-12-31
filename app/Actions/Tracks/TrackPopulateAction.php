<?php

namespace App\Actions\Tracks;

use App\Models\ScheduledConference;
use App\Models\Track;
use Lorisleiva\Actions\Concerns\AsAction;

class TrackPopulateAction
{
    use AsAction;

    public function handle(ScheduledConference $scheduledConference)
    {
        foreach ([
            'general' => 'General Track',
        ] as $abbr => $title) {
            $track = Track::firstOrCreate([
                'abbreviation' => $abbr,
                'title' => $title,
                'scheduled_conference_id' => $scheduledConference->id,
            ]);

            $track->setManyMeta($track->getAllMeta()->toArray());
        }
    }
}
