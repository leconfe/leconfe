<?php

use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\Track;
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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(ScheduledConference::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Proceeding::class)->nullable();
            $table->foreignIdFor(Track::class)->constrained();
            $table->integer('proceeding_order_column')->nullable();
            $table->boolean('revision_required')->default(false);
            $table->boolean('skipped_review')->default(false);
            $table->enum('stage', SubmissionStage::array())->default(SubmissionStage::Wizard->value);
            $table->enum('status', SubmissionStatus::array())->default(SubmissionStatus::Incomplete->value);
            $table->string('withdrawn_reason')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
