<?php

use App\Models\Conference;
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
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('role_name');
            $table->foreignIdFor(Conference::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Track::class)->nullable()->constrained()->nullOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('status')->default('pending');
            $table->foreignIdFor(User::class, 'invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['conference_id', 'scheduled_conference_id', 'track_id', 'status'], 'user_invitations_scope_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
