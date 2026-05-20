<?php

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
        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            // Shortened so the (product_name, product_folder, version)
            // composite unique index stays under the 1000-byte MySQL/MariaDB
            // MyISAM limit with utf8mb4.
            $table->string('product_name', 100);
            $table->string('product_folder', 80);
            $table->string('version', 40);
            $table->timestamp('installed_at');
            $table->timestamps();

            $table->unique(['product_name', 'product_folder', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
