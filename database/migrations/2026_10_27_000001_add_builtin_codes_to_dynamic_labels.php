<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['submission_file_types', 'author_roles', 'tracks'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'builtin_code')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->string('builtin_code')->nullable();
                    $scope = $tableName === 'author_roles' ? 'conference_id' : 'scheduled_conference_id';
                    $table->unique([$scope, 'builtin_code'], $tableName.'_builtin_unique');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['submission_file_types', 'author_roles', 'tracks'] as $tableName) {
            if (Schema::hasColumn($tableName, 'builtin_code')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropUnique($tableName.'_builtin_unique');
                    $table->dropColumn('builtin_code');
                });
            }
        }
    }
};
