<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_file_types', function (Blueprint $table) {
            $table->boolean('required')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('submission_file_types', function (Blueprint $table) {
            $table->dropColumn('required');
        });
    }
};
