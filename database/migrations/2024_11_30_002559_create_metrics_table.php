<?php

use App\Models\Conference;
use App\Models\ScheduledConference;
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
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->foreignIdFor(Conference::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->morphs('model');
            $table->date('log_at');
            $table->unsignedInteger('metric')->default(0);

            $table->index(['conference_id']);
            $table->index(['scheduled_conference_id']);
            $table->index(['event']);
            $table->index(['log_at']);
            
            $table->unique(['conference_id', 'scheduled_conference_id', 'event', 'log_at', 'model_type', 'model_id'], 'metrics_cselmm_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
