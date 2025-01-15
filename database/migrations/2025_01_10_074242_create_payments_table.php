<?php

use App\Models\Conference;
use App\Models\ScheduledConference;
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
        Schema::create('payment_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conference::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained();
            $table->unsignedInteger('type');
            $table->morphs('model');
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_completed', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conference::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('type');
            $table->morphs('model');
            $table->double('amount');
            $table->string('currency');
            $table->string('payment_method');
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('payment_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conference::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained();
            $table->string('name');
            $table->unsignedInteger('type');
            $table->double('amount');
            $table->string('currency');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_public')->default(false);
            $table->integer('limit')->default(0);
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_fees');
        Schema::dropIfExists('payment_queues');
        Schema::dropIfExists('payment_completed');
    }
};
