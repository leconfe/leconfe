<?php

use App\Constants\SubmissionFileCategory;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('submission_review_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Submission::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('status')->default('Open');
            $table->foreignIdFor(User::class, 'triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('default_file_ids')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'round_number']);
            $table->index(['submission_id', 'status']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('review_round_id')
                ->nullable()
                ->after('submission_id');

            $table->index(['submission_id', 'review_round_id']);
            $table->index(['submission_id', 'user_id', 'review_round_id'], 'reviews_submission_user_round_index');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('review_round_id')
                    ->references('id')
                    ->on('submission_review_rounds')
                    ->nullOnDelete();
            }
        });

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

        $now = now();

        $this->createFirstRounds(
            DB::table('reviews')
                ->select('submission_id')
                ->distinct(),
            $now,
            'reviews.submission_id'
        );

        $this->createFirstRounds(
            DB::table('submission_files')
                ->select('submission_id')
                ->whereIn('category', $this->reviewStageFileCategories())
                ->distinct(),
            $now,
            'submission_files.submission_id'
        );

        $this->createFirstRounds(
            DB::table('discussion_topics')
                ->select('submission_id')
                ->where('stage', SubmissionStage::PeerReview->value)
                ->distinct(),
            $now,
            'discussion_topics.submission_id'
        );

        $this->createFirstRounds(
            DB::table('submissions')
                ->select('id as submission_id')
                ->where('stage', SubmissionStage::PeerReview->value)
                ->whereIn('status', [SubmissionStatus::OnReview->value, SubmissionStatus::OnPayment->value]),
            $now,
            'submissions.id',
            'id'
        );

        $this->backfillRoundContext($now);
    }

    protected function createFirstRounds(Builder $submissionQuery, mixed $now, string $sourceColumn, string $orderColumn = 'submission_id'): void
    {
        $submissionQuery
            ->whereNotExists(function ($query) use ($sourceColumn) {
                $query->selectRaw(1)
                    ->from('submission_review_rounds')
                    ->whereColumn('submission_review_rounds.submission_id', $sourceColumn);
            })
            ->orderBy($orderColumn)
            ->lazy(500)
            ->each(function ($item) use ($now) {
                DB::table('submission_review_rounds')->insert([
                    'submission_id' => $item->submission_id,
                    'round_number' => 1,
                    'status' => 'Open',
                    'opened_at' => $now,
                    'closed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    protected function backfillRoundContext(mixed $now): void
    {
        DB::table('submission_review_rounds')
            ->select('submission_id', DB::raw('MIN(id) as first_round_id'))
            ->groupBy('submission_id')
            ->orderBy('submission_id')
            ->lazy(500)
            ->each(function ($item) use ($now) {
                DB::table('reviews')
                    ->where('submission_id', $item->submission_id)
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $item->first_round_id,
                        'updated_at' => $now,
                    ]);

                $fileIds = DB::table('submission_files')
                    ->where('submission_id', $item->submission_id)
                    ->whereIn('category', $this->reviewStageFileCategories())
                    ->whereNull('review_round_id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                DB::table('submission_files')
                    ->where('submission_id', $item->submission_id)
                    ->whereIn('category', $this->reviewStageFileCategories())
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $item->first_round_id,
                        'updated_at' => $now,
                    ]);

                $this->mergeDefaultFileIds($item->first_round_id, $fileIds, $now);

                DB::table('discussion_topics')
                    ->where('submission_id', $item->submission_id)
                    ->where('stage', SubmissionStage::PeerReview->value)
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $item->first_round_id,
                        'updated_at' => $now,
                    ]);
            });
    }

    protected function mergeDefaultFileIds(int $roundId, Collection $fileIds, mixed $now): void
    {
        if ($fileIds->isEmpty()) {
            return;
        }

        $round = DB::table('submission_review_rounds')
            ->where('id', $roundId)
            ->first();

        $defaultFileIds = collect(json_decode($round->default_file_ids ?? '[]', true) ?: [])
            ->merge($fileIds)
            ->unique()
            ->values()
            ->all();

        DB::table('submission_review_rounds')
            ->where('id', $roundId)
            ->update([
                'default_file_ids' => json_encode($defaultFileIds),
                'updated_at' => $now,
            ]);
    }

    protected function reviewStageFileCategories(): array
    {
        return array_values(array_unique([
            SubmissionFileCategory::REVIEW_FILES,
            'paper-files',
            SubmissionFileCategory::REVISION_FILES,
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discussion_topics', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['review_round_id']);
            }
            $table->dropIndex('discussion_topics_review_round_id_index');
            $table->dropColumn('review_round_id');
        });

        Schema::table('submission_files', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['review_round_id']);
            }
            $table->dropIndex('submission_files_review_round_id_index');
            $table->dropColumn('review_round_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_submission_user_round_index');
            $table->dropIndex(['submission_id', 'review_round_id']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['review_round_id']);
            }
            $table->dropColumn('review_round_id');
        });

        Schema::dropIfExists('submission_review_rounds');
    }
};
