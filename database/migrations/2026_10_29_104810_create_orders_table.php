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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('bracket_role')->nullable();
            $table->string('symbol', 10);
            $table->string('side');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->boolean('is_short')->default(false);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('limit_price_cents')->nullable();
            $table->unsignedBigInteger('stop_price_cents')->nullable();
            $table->unsignedBigInteger('trail_amount_cents')->nullable();
            $table->unsignedInteger('trail_percent_bps')->nullable();
            $table->unsignedBigInteger('trail_extreme_cents')->nullable();
            $table->timestamp('stop_triggered_at')->nullable();
            $table->unsignedBigInteger('fill_price_cents')->nullable();
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->timestamp('filled_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'symbol']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
