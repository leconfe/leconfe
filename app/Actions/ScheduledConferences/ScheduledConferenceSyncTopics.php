<?php

namespace App\Actions\ScheduledConferences;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ScheduledConferenceSyncTopics
{
    use AsAction;

    public function handle($scheduledConference, $topics = [])
    {
        $oldTopicIds = $scheduledConference->topics()
            ->withoutGlobalScopes()
            ->pluck('id')
            ->toArray();

        $topicsToSync = array_map('strtolower', $topics);

        DB::transaction(function () use ($scheduledConference, $oldTopicIds, $topicsToSync) {
            $scheduledConference->syncTopics($topicsToSync);

            // Delete orphaned topics
            Topic::withoutGlobalScopes()
                ->whereIn('id', $oldTopicIds)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('topicables')
                        ->whereColumn('topicables.topic_id', 'topics.id');
                })
                ->delete();
        });
    }
}
