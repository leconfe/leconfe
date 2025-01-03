<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Permission;
use App\Models\Review;
use App\Models\Track;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Upgrade120Beta3 extends UpgradeBase
{
    public function run(): void
    {
        $this->modifyReviews();
        $this->modifyPermissions();
        $this->modifyTracks();
        $this->updateReviewTable();
    }

    protected function modifyReviews()
    {
        foreach (Review::with(['meta'])->lazy() as $review) {
            $review->setMeta('review_for_author_editor', $review->review_for_author_editor);
            $review->setMeta('review_editor', $review->review_editor);
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
        ])->each(fn($name) => Permission::firstOrCreate([
            'name' => $name,
        ]));
    }

    protected function modifyTracks()
    {
        Track::with(['meta'])->lazy()->each(function ($track) {
            $track->setManyMeta($track->getAllMeta()->toArray());
        });
    }

    protected function updateReviewTable()
    {
        Schema::table('reviews', function (Blueprint $table) {
            
            if (! Schema::hasColumn('reviews', 'score')) {
                $table->unsignedInteger('score')->nullable()->after('quality');
            }
            if (!Schema::hasColumn('reviews', 'date_acknowledged')) {
                $table->timestamp('date_acknowledged')->nullable()->after('date_completed');
            }
            
            $table->removeColumn('review_for_author_editor');
            $table->removeColumn('review_editor');
        });
    }
}
