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
            if (Track::where('scheduled_conference_id', $scheduledConference->id)
                ->where('builtin_code', 'general_track')->exists()) {
                continue;
            }

            $track = Track::firstOrCreate([
                'abbreviation' => $abbr,
                'title' => $title,
                'scheduled_conference_id' => $scheduledConference->id,
            ]);

            if ($track->wasRecentlyCreated) {
                $track->forceFill(['builtin_code' => 'general_track'])->save();
                $track->setManyMeta($track->getAllMeta()->toArray());
            }
        }
    }
}
