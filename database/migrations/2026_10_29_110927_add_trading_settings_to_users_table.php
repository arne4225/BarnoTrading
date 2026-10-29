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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('commission_cents')->nullable();
            $table->unsignedInteger('slippage_bps')->nullable();
            $table->unsignedInteger('risk_per_trade_bps')->default(100);
            $table->unsignedInteger('max_position_percent')->default(25);
            $table->unsignedInteger('min_risk_reward_hundredths')->default(100);
            $table->boolean('warn_without_stop_loss')->default(true);
            $table->unsignedInteger('daily_loss_limit_bps')->nullable();
            $table->boolean('leaderboard_visible')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'commission_cents', 'slippage_bps', 'risk_per_trade_bps', 'max_position_percent',
                'min_risk_reward_hundredths', 'warn_without_stop_loss', 'daily_loss_limit_bps', 'leaderboard_visible',
            ]);
        });
    }
};
