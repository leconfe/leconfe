<?php

use App\Models\Conference;
use App\Models\Enums\ContentType;
use App\Models\Serie;
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
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conference::class);
            $table->foreignIdFor(Serie::class)->nullable()->default(0);
            $table->string('title');
            $table->string('slug');
            $table->timestamps();


            $table->unique(['conference_id', 'serie_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
