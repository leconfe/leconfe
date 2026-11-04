<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_fee_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNull('payment_fee_id')->exists()) {
            throw new RuntimeException('Free registrations exist; resolve them before reverting this migration.');
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_fee_id')->nullable(false)->change();
        });
    }
};
