<?php

use App\Models\Presentation;
use App\Models\PresentationComment;
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
        Schema::create('presentation_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Presentation::class)->constrained();
            $table->foreignIdFor(User::class)->constrained();
            $table->boolean('is_hidden')->default(false);
            $table->foreignIdFor(PresentationComment::class, 'parent_id')->nullable()->constrained('presentation_comments');

            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentation_comments');
    }
};
