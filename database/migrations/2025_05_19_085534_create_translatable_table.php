<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('translate')) {
            Schema::create('translate', function (Blueprint $table) {
                $table->id();

                $table->morphs('translatable');
                $table->string('locale')->index();
                $table->string('key')->index();
                $table->longtext('value')->nullable();

                $table->unique(['translatable_type', 'translatable_id', 'locale', 'key']);
                $table->index(['key', 'translatable_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('translate');
    }
};
