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
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['scheduled_conference_id', 'slug']);
            $table->index(['scheduled_conference_id']);
            $table->index(['scheduled_conference_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
