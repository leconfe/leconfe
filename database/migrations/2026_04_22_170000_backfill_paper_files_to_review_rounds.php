<?php

use App\Constants\SubmissionFileCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('submission_review_rounds')
            ->select('submission_id', DB::raw('MIN(id) as first_round_id'))
            ->groupBy('submission_id')
            ->get()
            ->each(function ($item) use ($now) {
                DB::table('submission_files')
                    ->where('submission_id', $item->submission_id)
                    ->where('category', SubmissionFileCategory::PAPER_FILES)
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $item->first_round_id,
                        'updated_at' => $now,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill migration is intentionally irreversible.
    }
};
