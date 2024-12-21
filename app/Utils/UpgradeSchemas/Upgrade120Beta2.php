<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Permission;
use App\Models\Review;

class Upgrade120Beta2 extends UpgradeBase
{
    public function run(): void
    {
        $this->modifyReviews();
        $this->modifyPermissions();
    }

    protected function modifyReviews()
    {
        foreach  (Review::with(['meta'])->lazy() as $review)  {
            $review->setMeta('review_mode', Review::MODE_OPEN);
        }
    }

    protected function modifyPermissions()
    {
        Permission::query()
            ->whereIn('name', [
                'Submission:acceptAbstract',
                'Submission:declineAbstract',
            ])
            ->delete();

        collect([
            'Submission:declineAbstract',
            'Submission:submitAs',
        ])->each(fn ($name) => Permission::firstOrCreate([
            'name' => $name,
        ]));
    }
}
