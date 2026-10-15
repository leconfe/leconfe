<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ban.table'), function (Blueprint $table) {
            $table->unsignedBigInteger('conference_id')->nullable();
            $table->unsignedBigInteger('scheduled_conference_id')->nullable();
            $table->index(['conference_id', 'scheduled_conference_id'], 'bans_scope_index');
        });
    }

    public function down(): void
    {
        // Scoped restrictions must not become global when their scope columns are removed.
        DB::table(config('ban.table'))
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNotNull('conference_id')->orWhereNotNull('scheduled_conference_id'))
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        Schema::table(config('ban.table'), function (Blueprint $table) {
            $table->dropIndex('bans_scope_index');
            $table->dropColumn(['conference_id', 'scheduled_conference_id']);
        });
    }
};
