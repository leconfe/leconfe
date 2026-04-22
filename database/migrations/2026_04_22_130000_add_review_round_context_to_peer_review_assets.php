<?php

use App\Constants\SubmissionFileCategory;
use App\Models\Enums\SubmissionStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->unsignedBigInteger('review_round_id')
                ->nullable()
                ->after('submission_id');

            $table->index('review_round_id', 'submission_files_review_round_id_index');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('review_round_id')
                    ->references('id')
                    ->on('submission_review_rounds')
                    ->nullOnDelete();
            }
        });

        Schema::table('discussion_topics', function (Blueprint $table) {
            $table->unsignedBigInteger('review_round_id')
                ->nullable()
                ->after('submission_id');

            $table->index('review_round_id', 'discussion_topics_review_round_id_index');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('review_round_id')
                    ->references('id')
                    ->on('submission_review_rounds')
                    ->nullOnDelete();
            }
        });

        DB::table('submission_review_rounds')
            ->select('submission_id', DB::raw('MIN(id) as first_round_id'))
            ->groupBy('submission_id')
            ->get()
            ->each(function ($item) {
                DB::table('submission_files')
                    ->where('submission_id', $item->submission_id)
                    ->where('category', SubmissionFileCategory::REVISION_FILES)
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $item->first_round_id,
                        'updated_at' => now(),
                    ]);

                DB::table('discussion_topics')
                    ->where('submission_id', $item->submission_id)
                    ->where('stage', SubmissionStage::PeerReview->value)
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $item->first_round_id,
                        'updated_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['review_round_id']);
            }

            $table->dropIndex('submission_files_review_round_id_index');
            $table->dropColumn('review_round_id');
        });

        Schema::table('discussion_topics', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['review_round_id']);
            }

            $table->dropIndex('discussion_topics_review_round_id_index');
            $table->dropColumn('review_round_id');
        });
    }
};
