<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedInteger('order_column')->nullable()->after('name');
        });

        DB::table('topics')->orderBy('id')->lazyById()->each(function ($topic, int $index) {
            DB::table('topics')
                ->where('id', $topic->id)
                ->update(['order_column' => $index + 1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('order_column');
        });
    }
};
