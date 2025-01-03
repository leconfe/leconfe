<?php

use App\Constants\ReviewerStatus;
use App\Models\Review;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Submission::class)->constrained();
            $table->foreignIdFor(User::class)->constrained();
            $table->string('recommendation')->nullable();
            $table->string('status')->default(ReviewerStatus::PENDING);
            $table->integer('quality')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->longText('review_author_editor')->nullable();
            $table->longText('review_editor')->nullable();
            $table->timestamp('date_assigned')->useCurrent();
            $table->timestamp('date_confirmed')->nullable();
            $table->timestamp('date_completed')->nullable();
            $table->timestamp('date_acknowledged')->nullable();
            $table->timestamps();
        });

        Schema::create('reviewer_assigned_files', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Review::class)->constrained();
            $table->foreignIdFor(SubmissionFile::class)->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
