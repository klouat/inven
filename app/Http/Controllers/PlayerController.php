<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerRod;
use App\Models\PlayerInventory;
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
        $master_rods = \App\Models\MasterRod::all()->keyBy('name');

        // Search inputs
        $searchInv = $request->query('search_inv');

        if ($selected_name && in_array($selected_name, $tracked_names)) {
            $player_data = Player::with(['rods'])->where('player_name', $selected_name)->first();
            
            if ($player_data) {
                // Inventory with search
                $invQuery = $player_data->inventories();
                if ($searchInv) {
                    $invQuery->where('name', 'like', '%' . $searchInv . '%');
                }
                
                $inventories = $invQuery->paginate(30)->withQueryString();
            }
        }
        
        // If it's an AJAX request just return the inventory partial (or dashboard and we extract it on JS side)
        // Extracting on JS side is perfectly fine and avoids extra views!
        
        return view('dashboard', compact('tracked_players', 'player_data', 'selected_name', 'inventories', 'master_rods', 'searchInv'));
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

            // Re-sync rods logic seamlessly
            $player->rods()->delete();
            $rods_data = [];
            
            if (isset($json_data['rods'])) {
                foreach ($json_data['rods'] as $rod) {
                    $rods_data[] = [
                        'player_id' => $player->id,
                        'name' => $rod['Name'],
                        'icon' => $rod['Icon'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                PlayerRod::insert($rods_data);
            }

            // Batched inventory insertion for optimization
            $player->inventories()->delete();
            $inventory_data = [];
            
            if (isset($json_data['inventory'])) {
                foreach ($json_data['inventory'] as $item) {
                    $inventory_data[] = [
                        'player_id' => $player->id,
                        'sparkling' => $item['sparkling'] ?? false,
                        'name' => $item['name'],
                        'weight' => $item['weight'] ?? 0,
                        'shiny' => $item['shiny'] ?? false,
                        'stack' => $item['stack'] ?? 1,
                        'mutation' => $item['mutation'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                PlayerInventory::insert($inventory_data);
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
