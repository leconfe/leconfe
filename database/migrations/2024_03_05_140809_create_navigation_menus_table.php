<?php

use App\Models\Conference;
use App\Models\NavigationMenu;
use App\Models\ScheduledConference;
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
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('handle');
            $table->timestamps();

            $table->unique(['scheduled_conference_id', 'handle'], 'unique_navigation_menus');
        });

        Schema::create('navigation_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(NavigationMenu::class);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('label');
            $table->string('type');
            $table->unsignedInteger('order_column');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_menus');
    }
};
