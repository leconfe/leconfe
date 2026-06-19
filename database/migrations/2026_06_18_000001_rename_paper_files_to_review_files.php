<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('submission_review_rounds', 'name')) {
            Schema::table('submission_review_rounds', function (Blueprint $table) {
                $table->string('name')->nullable()->after('round_number');
            });
        }

        DB::table('submission_files')
            ->where('category', 'paper-files')
            ->update(['category' => 'review-files']);

        DB::table('media')
            ->where('collection_name', 'paper-files')
            ->update(['collection_name' => 'review-files']);
    }

    public function down(): void
    {
        DB::table('submission_files')
            ->where('category', 'review-files')
            ->update(['category' => 'paper-files']);

        DB::table('media')
            ->where('collection_name', 'review-files')
            ->update(['collection_name' => 'paper-files']);

        if (Schema::hasColumn('submission_review_rounds', 'name')) {
            Schema::table('submission_review_rounds', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
};
