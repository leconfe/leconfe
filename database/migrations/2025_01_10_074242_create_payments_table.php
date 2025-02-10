<?php

use App\Models\Conference;
use App\Models\PaymentFee;
use App\Models\ScheduledConference;
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
        Schema::create('payment_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conference::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained();
            $table->string('name');
            $table->unsignedInteger('type');
            $table->double('amount');
            $table->string('currency');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_public')->default(false);
            $table->integer('limit')->default(0);
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('payment_fee_form_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PaymentFee::class)->constrained();
            $table->unsignedInteger('order_column')->nullable();
            $table->unsignedInteger('type')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('included')->default(false);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conference::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduledConference::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(PaymentFee::class)->constrained();
            $table->unsignedInteger('type');
            $table->nullableMorphs('model');
            $table->double('amount');
            $table->string('currency');
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_fee_form_items');
        Schema::dropIfExists('payment_fees');
    }
};
