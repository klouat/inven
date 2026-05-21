<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('player_rods', 'sync_key')) {
            Schema::table('player_rods', function (Blueprint $table) {
                $table->string('sync_key', 40)->nullable()->after('player_id');
            });
        }

        if (!Schema::hasColumn('player_inventories', 'sync_key')) {
            Schema::table('player_inventories', function (Blueprint $table) {
                $table->string('sync_key', 40)->nullable()->after('player_id');
            });
        }

        if (!Schema::hasColumn('player_storages', 'sync_key')) {
            Schema::table('player_storages', function (Blueprint $table) {
                $table->string('sync_key', 40)->nullable()->after('player_id');
            });
        }

        $this->backfillTable('player_rods', ['name', 'icon']);
        $this->backfillTable('player_inventories', ['sparkling', 'name', 'weight', 'shiny', 'stack', 'mutation', 'favourited']);
        $this->backfillTable('player_storages', ['sparkling', 'name', 'weight', 'shiny', 'stack', 'mutation', 'favourited']);

        if (!$this->indexExists('player_rods', 'player_rods_player_sync_key_unique')) {
            Schema::table('player_rods', function (Blueprint $table) {
                $table->unique(['player_id', 'sync_key'], 'player_rods_player_sync_key_unique');
            });
        }

        if (!$this->indexExists('player_inventories', 'player_inventories_player_sync_key_unique')) {
            Schema::table('player_inventories', function (Blueprint $table) {
                $table->unique(['player_id', 'sync_key'], 'player_inventories_player_sync_key_unique');
            });
        }

        if (!$this->indexExists('player_storages', 'player_storages_player_sync_key_unique')) {
            Schema::table('player_storages', function (Blueprint $table) {
                $table->unique(['player_id', 'sync_key'], 'player_storages_player_sync_key_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('player_storages', 'player_storages_player_sync_key_unique')) {
            Schema::table('player_storages', function (Blueprint $table) {
                $table->dropUnique('player_storages_player_sync_key_unique');
            });
        }

        if ($this->indexExists('player_inventories', 'player_inventories_player_sync_key_unique')) {
            Schema::table('player_inventories', function (Blueprint $table) {
                $table->dropUnique('player_inventories_player_sync_key_unique');
            });
        }

        if ($this->indexExists('player_rods', 'player_rods_player_sync_key_unique')) {
            Schema::table('player_rods', function (Blueprint $table) {
                $table->dropUnique('player_rods_player_sync_key_unique');
            });
        }

        if (Schema::hasColumn('player_storages', 'sync_key')) {
            Schema::table('player_storages', function (Blueprint $table) {
                $table->dropColumn('sync_key');
            });
        }

        if (Schema::hasColumn('player_inventories', 'sync_key')) {
            Schema::table('player_inventories', function (Blueprint $table) {
                $table->dropColumn('sync_key');
            });
        }

        if (Schema::hasColumn('player_rods', 'sync_key')) {
            Schema::table('player_rods', function (Blueprint $table) {
                $table->dropColumn('sync_key');
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

    protected function backfillTable(string $table, array $fields): void
    {
        DB::table($table)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $fields) {
                static $occurrences = [];

                foreach ($rows as $row) {
                    $player_occurrences = $occurrences[$table][$row->player_id] ?? [];
                    $payload = [];

                    foreach ($fields as $field) {
                        $payload[$field] = $row->{$field};
                    }

                    $sync_key = $this->makeSyncKey($payload, $player_occurrences);
                    $occurrences[$table][$row->player_id] = $player_occurrences;

                    DB::table($table)->where('id', $row->id)->update(['sync_key' => $sync_key]);
                }
            });
    }

    protected function makeSyncKey(array $attributes, array &$occurrences): string
    {
        $signature = json_encode([
            'sparkling' => (bool) ($attributes['sparkling'] ?? false),
            'name' => trim((string) ($attributes['name'] ?? '')),
            'weight' => round((float) ($attributes['weight'] ?? 0), 6),
            'shiny' => (bool) ($attributes['shiny'] ?? false),
            'stack' => max(1, (int) ($attributes['stack'] ?? 1)),
            'mutation' => array_key_exists('mutation', $attributes) && $attributes['mutation'] !== null
                ? trim((string) $attributes['mutation'])
                : null,
            'favourited' => (bool) ($attributes['favourited'] ?? false),
            'icon' => (string) ($attributes['icon'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $occurrences[$signature] = ($occurrences[$signature] ?? 0) + 1;

        return sha1($signature.'#'.$occurrences[$signature]);
    }
};
