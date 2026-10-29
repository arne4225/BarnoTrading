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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 10);
            $table->string('direction');
            $table->unsignedInteger('peak_quantity')->default(0);
            $table->unsignedBigInteger('open_quantity')->default(0);
            $table->unsignedBigInteger('open_value_cents')->default(0);
            $table->unsignedBigInteger('close_quantity')->default(0);
            $table->unsignedBigInteger('close_value_cents')->default(0);
            $table->bigInteger('realized_pnl_cents')->default(0);
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->text('entry_reason')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('self_rating')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
