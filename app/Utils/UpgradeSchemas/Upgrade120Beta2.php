<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Discussion;
use App\Models\Review;

class Upgrade120Beta2 extends UpgradeBase
{
    public function run(): void
    {
        foreach(Review::with(['meta'])->lazy() as $review){
            $review->setMeta('review_mode', Review::MODE_OPEN);
        }
    }
}
