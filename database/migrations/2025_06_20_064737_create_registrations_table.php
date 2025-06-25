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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ScheduledConference::class)->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('given_name');
            $table->string('family_name')->nullable();
            $table->double('cost');
            $table->string('currency');
            $table->string('type');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('withdraw')->nullable();
            $table->timestamps();
        });

        Schema::create('registration_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ScheduledConference::class)->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('type');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });
        
        Schema::create('registration_types', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ScheduledConference::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->double('cost');
            $table->string('currency');
            $table->integer('limit')->nullable();
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamp('from')->nullable();
            $table->timestamp('to')->nullable();
            $table->timestamps(); 
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_types');
        Schema::dropIfExists('registration_forms');
        Schema::dropIfExists('registrations');
    }
};
