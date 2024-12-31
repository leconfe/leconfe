<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Permission;
use App\Models\Review;
use App\Models\Track;

class Upgrade120Beta2 extends UpgradeBase
{
    public function run(): void
    {
        $this->modifyReviews();
        $this->modifyPermissions();
        $this->modifyTracks();
    }

    protected function modifyReviews()
    {
        foreach (Review::with(['meta'])->lazy() as $review) {
            $review->setMeta('review_mode', Review::MODE_OPEN);
        }
    }

    protected function modifyPermissions()
    {
        Permission::query()
            ->whereIn('name', [
                'Submission:acceptAbstract',
                'Submission:declineAbstract',
                'Submission:publish',
                'Submission:unpublish',
                'Submission:assignReviewer',
                'Submission:editReviewer',
                'Submission:cancelReviewer',
                'Submission:emailReviewer',
            ])
            ->delete();

        collect([
            'Submission:submitAs',
        ])->each(fn ($name) => Permission::firstOrCreate([
            'name' => $name,
        ]));
    }

    protected function modifyTracks()
    {
        Track::with(['meta'])->lazy()->each(function ($track) {
            $track->setManyMeta($track->getAllMeta()->toArray());
        });
    }
}
