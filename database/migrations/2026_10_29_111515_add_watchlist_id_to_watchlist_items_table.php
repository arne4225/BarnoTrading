<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->foreignId('watchlist_id')->nullable()->constrained()->cascadeOnDelete();
        });

        // Bestaande items verhuizen naar een standaardlijst per gebruiker.
        DB::table('watchlist_items')->distinct()->pluck('user_id')->each(function ($userId) {
            $listId = DB::table('watchlists')->insertGetId([
                'user_id' => $userId, 'name' => 'Standaard', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('watchlist_items')->where('user_id', $userId)->update(['watchlist_id' => $listId]);
        });

        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'symbol']);
            $table->unique(['watchlist_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->dropUnique(['watchlist_id', 'symbol']);
            $table->dropConstrainedForeignId('watchlist_id');
            $table->unique(['user_id', 'symbol']);
        });
    }
};
