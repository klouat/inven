<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerRod;
use App\Models\PlayerInventory;
use App\Models\PlayerStorage;
use App\Models\TrackedPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Fetch players the current user is tracking
        $tracked_players = $user->trackedPlayers;
        $tracked_names = $tracked_players->pluck('player_name')->toArray();
        
        $selected_name = $request->query('player');
        if (!$selected_name && count($tracked_names) > 0) {
            $selected_name = $tracked_names[0];
        }

        $player_data = null;
        $inventories = null;
        $storages = null;
        $active_view = $request->query('view', 'inventory');
        if (!in_array($active_view, ['inventory', 'storage'], true)) {
            $active_view = 'inventory';
        }

        // Search inputs
        $searchItem     = $request->query('search_item', '');
        $ignore_mutation = $request->query('ignore_mutation') === 'true';
        $rarity_filter  = $request->query('rarity_filter', '');
        // Fetch Master Data directly (Cache exceeds cPanel memcached/packet limits)
        $master_rods = \App\Models\MasterRod::all()->keyBy('name');
        
        $master_fishes = \App\Models\Fish::all()->keyBy(function($fish) {
            return trim($fish->name);
        });
        
        $master_mutations = \App\Models\Mutation::all()->keyBy(function($mutation) {
            return trim($mutation->name);
        });

        $rarity_options = \App\Models\Fish::whereNotNull('rarity')
            ->distinct()
            ->orderBy('rarity')
            ->pluck('rarity');

        if ($selected_name && in_array($selected_name, $tracked_names)) {
            $player_data = Player::with(['rods'])->where('player_name', $selected_name)->first();
            
            if ($player_data) {
                if ($active_view === 'inventory') {
                    $invQuery = $player_data->inventories();
                    if ($searchItem) {
                        $invQuery->where('player_inventories.name', 'like', '%' . $searchItem . '%');
                    }

                    if ($rarity_filter) {
                        $invQuery->join('fishes', 'player_inventories.name', '=', 'fishes.name')
                            ->where('fishes.rarity', $rarity_filter)
                            ->select('player_inventories.*');
                    }

                    if ($ignore_mutation) {
                        $invQuery->select(
                            'player_inventories.name',
                            DB::raw('SUM(player_inventories.stack) as stack'),
                            DB::raw('SUM(player_inventories.weight) as weight'),
                            DB::raw('MAX(player_inventories.sparkling) as sparkling'),
                            DB::raw('MAX(player_inventories.shiny) as shiny'),
                            DB::raw('MAX(player_inventories.favourited) as favourited'),
                            DB::raw('MAX(player_inventories.mutation) as mutation')
                        )->groupBy('player_inventories.name');
                    }

                    $inventories = $invQuery->paginate(30)->withQueryString();
                } else {
                    $storageQuery = $player_data->storages()->latest('id');
                    if ($searchItem) {
                        $storageQuery->where('player_storages.name', 'like', '%' . $searchItem . '%');
                    }

                    if ($rarity_filter) {
                        $storageQuery->join('fishes', 'player_storages.name', '=', 'fishes.name')
                            ->where('fishes.rarity', $rarity_filter)
                            ->select('player_storages.*');
                    }

                    if ($ignore_mutation) {
                        $storageQuery->select(
                            'player_storages.name',
                            DB::raw('SUM(player_storages.stack) as stack'),
                            DB::raw('SUM(player_storages.weight) as weight'),
                            DB::raw('MAX(player_storages.sparkling) as sparkling'),
                            DB::raw('MAX(player_storages.shiny) as shiny'),
                            DB::raw('MAX(player_storages.favourited) as favourited'),
                            DB::raw('MAX(player_storages.mutation) as mutation')
                        )->groupBy('player_storages.name');
                    }

                    $storages = $storageQuery->paginate(30)->withQueryString();
                }
            }
        }
        
        // If it's an AJAX request just return the inventory partial (or dashboard and we extract it on JS side)
        // Extracting on JS side is perfectly fine and avoids extra views!
        
        return view('dashboard', compact(
            'tracked_players', 'player_data', 'selected_name',
            'inventories', 'storages', 'master_rods', 'searchItem',
            'ignore_mutation', 'master_fishes', 'master_mutations',
            'rarity_filter', 'rarity_options', 'active_view'
        ));
    }

    public function track_player(Request $request)
    {
        $request->validate([
            'player_name' => 'required|string|max:255',
        ]);
        
        $user = Auth::user();
        
        // Store track using snake_case parameter
        TrackedPlayer::firstOrCreate([
            'user_id' => $user->id,
            'player_name' => $request->player_name
        ]);
        
        return back()->with('success', 'Now tracking player: ' . $request->player_name);
    }
    
    public function untrack_player(Request $request, $id)
    {
        $tracked = TrackedPlayer::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $name = $tracked->player_name;
        $tracked->delete();
        
        return back()->with('success', 'Untracked player: ' . $name);
    }

    public function upload_data_api(Request $request)
    {
        try {
            $json_data = $request->json()->all();

            if (empty($json_data)) {
                 $json_data = json_decode($request->getContent(), true);
            }

            if (!$json_data || !isset($json_data['playerName'])) {
                return response()->json(['error' => 'Invalid or unreadable JSON format provided.'], 400);
            }

            DB::beginTransaction();

            $player = Player::updateOrCreate(
                ['player_name' => $json_data['playerName']],
                ['coins' => $json_data['coins'] ?? 0]
            );
            $player->touch();

            // Re-sync rods logic seamlessly
            $player->rods()->delete();
            
            if (!empty($json_data['rods'])) {
                $rods_chunks = array_chunk($json_data['rods'], 500);
                foreach ($rods_chunks as $chunk) {
                    $rods_data = [];
                    foreach ($chunk as $rod) {
                        $rods_data[] = [
                            'player_id' => $player->id,
                            'name' => $rod['name'] ?? $rod['Name'] ?? 'Unknown Rod',
                            'icon' => $rod['icon'] ?? $rod['Icon'] ?? '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    PlayerRod::insert($rods_data);
                }
            }

            // Batched inventory insertion for optimization
            $player->inventories()->delete();
            
            if (!empty($json_data['inventory'])) {
                $inventory_chunks = array_chunk($json_data['inventory'], 500);
                foreach ($inventory_chunks as $chunk) {
                    $inventory_data = [];
                    foreach ($chunk as $item) {
                        $inventory_data[] = [
                            'player_id' => $player->id,
                            'sparkling' => $item['sparkling'] ?? false,
                            'name' => $item['name'],
                            'weight' => $item['weight'] ?? 0,
                            'shiny' => $item['shiny'] ?? false,
                            'stack' => $item['stack'] ?? 1,
                            'mutation' => $item['mutation'] ?? null,
                            'favourited' => $item['favourited'] ?? false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    PlayerInventory::insert($inventory_data);
                }
            }

            // Batched storage insertion mirrors inventory payload handling
            $player->storages()->delete();

            if (!empty($json_data['storage'])) {
                $storage_chunks = array_chunk($json_data['storage'], 500);
                foreach ($storage_chunks as $chunk) {
                    $storage_data = [];
                    foreach ($chunk as $item) {
                        $storage_data[] = [
                            'player_id' => $player->id,
                            'sparkling' => $item['sparkling'] ?? false,
                            'name' => $item['name'],
                            'weight' => $item['weight'] ?? 0,
                            'shiny' => $item['shiny'] ?? false,
                            'stack' => $item['stack'] ?? 1,
                            'mutation' => $item['mutation'] ?? null,
                            'favourited' => $item['favourited'] ?? false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    PlayerStorage::insert($storage_data);
                }
            }

            DB::commit();

            return response()->json(['success' => 'Player data sync for '. $json_data['playerName'] .' was a success!']);

        } catch (Exception $error) {
            DB::rollBack();
            Log::error(
                "Failed to process JSON player data via API",
                [
                    "error" => $error->getMessage()
                ]
            );
            return response()->json(['error' => 'Critical Error: Failed to import data. Administrators, please check logs.'], 500);
        }
    }
}
