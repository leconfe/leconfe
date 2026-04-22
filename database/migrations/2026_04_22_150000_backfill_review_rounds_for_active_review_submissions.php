<?php

use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\SubmissionReviewRound;
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

        DB::table('submissions')
            ->select('id')
            ->where('stage', SubmissionStage::PeerReview->value)
            ->whereIn('status', [
                SubmissionStatus::OnReview->value,
                SubmissionStatus::OnPayment->value,
            ])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('submission_review_rounds')
                    ->whereColumn('submission_review_rounds.submission_id', 'submissions.id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($submissions) use ($now) {
                $rows = $submissions
                    ->map(fn ($submission) => [
                        'submission_id' => $submission->id,
                        'round_number' => 1,
                        'status' => SubmissionReviewRound::STATUS_OPEN,
                        'opened_at' => $now,
                        'closed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if (! empty($rows)) {
                    DB::table('submission_review_rounds')->insert($rows);
                }
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
