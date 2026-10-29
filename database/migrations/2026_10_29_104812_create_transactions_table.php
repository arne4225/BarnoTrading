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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('symbol', 10);
            $table->string('side');
            $table->boolean('is_short')->default(false);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('price_cents');
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->bigInteger('realized_pnl_cents')->default(0);
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['user_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
