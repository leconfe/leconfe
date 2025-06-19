<?php

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
        Schema::create('submission_review_form_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ScheduledConference::class)->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->double('weight')->nullable();
            $table->unsignedInteger('type')->nullable();
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_review_form_items');
    }
};
