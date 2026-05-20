<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_rows_count')->default(0)->after('coins');
            $table->unsignedBigInteger('inventory_stack_count')->default(0)->after('inventory_rows_count');
            $table->unsignedBigInteger('inventory_total_value')->default(0)->after('inventory_stack_count');
            $table->unsignedBigInteger('storage_rows_count')->default(0)->after('inventory_total_value');
            $table->unsignedBigInteger('storage_stack_count')->default(0)->after('storage_rows_count');
            $table->unsignedBigInteger('storage_total_value')->default(0)->after('storage_stack_count');
        });

        Schema::table('player_inventories', function (Blueprint $table) {
            $table->index(['player_id', 'name'], 'player_inventories_player_name_idx');
        });

        Schema::table('player_storages', function (Blueprint $table) {
            $table->index(['player_id', 'name'], 'player_storages_player_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('player_storages', function (Blueprint $table) {
            $table->dropIndex('player_storages_player_name_idx');
        });

        Schema::table('player_inventories', function (Blueprint $table) {
            $table->dropIndex('player_inventories_player_name_idx');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_rows_count',
                'inventory_stack_count',
                'inventory_total_value',
                'storage_rows_count',
                'storage_stack_count',
                'storage_total_value',
            ]);
        });
    }
};
