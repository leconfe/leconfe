<?php

use App\Models\Submission;
use App\Models\User;
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

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('review_round_id')
                    ->references('id')
                    ->on('submission_review_rounds')
                    ->nullOnDelete();
            }
        });

        $now = now();

        DB::table('reviews')
            ->select('submission_id')
            ->distinct()
            ->orderBy('submission_id')
            ->pluck('submission_id')
            ->each(function ($submissionId) use ($now) {
                $roundId = DB::table('submission_review_rounds')->insertGetId([
                    'submission_id' => $submissionId,
                    'round_number' => 1,
                    'status' => 'Open',
                    'opened_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('reviews')
                    ->where('submission_id', $submissionId)
                    ->whereNull('review_round_id')
                    ->update([
                        'review_round_id' => $roundId,
                        'updated_at' => $now,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['submission_id', 'review_round_id']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['review_round_id']);
            }
            $table->dropColumn('review_round_id');
        });

        Schema::dropIfExists('submission_review_rounds');
    }
};
