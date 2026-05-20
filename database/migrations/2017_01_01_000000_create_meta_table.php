<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('meta')) {
            Schema::create('meta', function (Blueprint $table) {
                $table->increments('id');
                // Shortened so the (metable_type, metable_id, key) composite
                // unique index stays under the 1000-byte MySQL/MariaDB MyISAM
                // limit with utf8mb4 (4 bytes/char).
                $table->string('metable_type', 125);
                $table->unsignedBigInteger('metable_id');
                $table->string('type')->default('null');
                $table->string('key', 100)->index();
                $table->longtext('value');

                $table->unique(['metable_type', 'metable_id', 'key']);
                $table->index(['key', 'metable_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meta');
    }
}
