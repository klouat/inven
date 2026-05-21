<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('tracked_players', 'tracked_players_user_player_unique')) {
            Schema::table('tracked_players', function (Blueprint $table) {
                $table->unique(['user_id', 'player_name'], 'tracked_players_user_player_unique');
            });
        }

        if (!$this->indexExists('player_inventories', 'player_inventories_player_updated_id_idx')) {
            Schema::table('player_inventories', function (Blueprint $table) {
                $table->index(['player_id', 'updated_at', 'id'], 'player_inventories_player_updated_id_idx');
            });
        }

        if (!$this->indexExists('player_storages', 'player_storages_player_updated_id_idx')) {
            Schema::table('player_storages', function (Blueprint $table) {
                $table->index(['player_id', 'updated_at', 'id'], 'player_storages_player_updated_id_idx');
            });
        }

        if (!$this->indexExists('fishes', 'fishes_rarity_idx')) {
            Schema::table('fishes', function (Blueprint $table) {
                $table->index('rarity', 'fishes_rarity_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('fishes', 'fishes_rarity_idx')) {
            Schema::table('fishes', function (Blueprint $table) {
                $table->dropIndex('fishes_rarity_idx');
            });
        }

        if ($this->indexExists('player_storages', 'player_storages_player_updated_id_idx')) {
            Schema::table('player_storages', function (Blueprint $table) {
                $table->dropIndex('player_storages_player_updated_id_idx');
            });
        }

        if ($this->indexExists('player_inventories', 'player_inventories_player_updated_id_idx')) {
            Schema::table('player_inventories', function (Blueprint $table) {
                $table->dropIndex('player_inventories_player_updated_id_idx');
            });
        }

        if ($this->indexExists('tracked_players', 'tracked_players_user_player_unique')) {
            Schema::table('tracked_players', function (Blueprint $table) {
                $table->dropUnique('tracked_players_user_player_unique');
            });
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('".$table."')");

            foreach ($indexes as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return count(DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index])) > 0;
    }
};
