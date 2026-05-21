<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerUploadDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_snapshot_sync_reuses_inventory_and_storage_rows(): void
    {
        $payload = [
            'playerName' => 'SnapshotTester',
            'coins' => 12345,
            'rods' => [
                ['name' => 'Rod A', 'icon' => 'a.png'],
            ],
            'inventory' => [
                [
                    'name' => 'Trout',
                    'weight' => 2.5,
                    'stack' => 2,
                    'mutation' => null,
                    'shiny' => false,
                    'sparkling' => false,
                    'favourited' => true,
                ],
            ],
            'storage' => [
                [
                    'name' => 'Salmon',
                    'weight' => 3,
                    'stack' => 1,
                    'mutation' => null,
                    'shiny' => true,
                    'sparkling' => false,
                    'favourited' => false,
                ],
            ],
        ];

        $this->postJson('/api/upload-json', $payload)->assertOk();

        $player = Player::where('player_name', 'SnapshotTester')->firstOrFail();
        $inventory_id = $player->inventories()->value('id');
        $storage_id = $player->storages()->value('id');
        $rod_id = $player->rods()->value('id');

        $this->postJson('/api/upload-json', $payload)->assertOk();

        $player->refresh();

        $this->assertSame($inventory_id, $player->inventories()->value('id'));
        $this->assertSame($storage_id, $player->storages()->value('id'));
        $this->assertSame($rod_id, $player->rods()->value('id'));
        $this->assertDatabaseCount('player_inventories', 1);
        $this->assertDatabaseCount('player_storages', 1);
        $this->assertDatabaseCount('player_rods', 1);
    }

    public function test_snapshot_sync_deletes_rows_missing_from_latest_payload(): void
    {
        $initial_payload = [
            'playerName' => 'DeltaTester',
            'coins' => 100,
            'inventory' => [
                ['name' => 'Trout', 'weight' => 1.5, 'stack' => 1],
                ['name' => 'Carp', 'weight' => 2.5, 'stack' => 1],
            ],
            'storage' => [
                ['name' => 'Salmon', 'weight' => 4, 'stack' => 1],
            ],
        ];

        $this->postJson('/api/upload-json', $initial_payload)->assertOk();

        $next_payload = [
            'playerName' => 'DeltaTester',
            'coins' => 200,
            'inventory' => [
                ['name' => 'Carp', 'weight' => 2.5, 'stack' => 1],
            ],
            'storage' => [],
        ];

        $this->postJson('/api/upload-json', $next_payload)->assertOk();

        $player = Player::where('player_name', 'DeltaTester')->firstOrFail();

        $this->assertSame(1, $player->inventories()->count());
        $this->assertSame('Carp', $player->inventories()->value('name'));
        $this->assertSame(0, $player->storages()->count());
        $this->assertSame(1, (int) $player->inventory_rows_count);
        $this->assertSame(1, (int) $player->inventory_stack_count);
        $this->assertSame(0, (int) $player->storage_rows_count);
    }
}
