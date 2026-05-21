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
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Exception;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Fetch only the fields the dashboard needs for the tracked-player switcher.
        $tracked_players = $user->trackedPlayers()
            ->select(['id', 'player_name'])
            ->orderBy('player_name')
            ->get();
        $tracked_names = $tracked_players->pluck('player_name')->toArray();
        
        $selected_name = $request->query('player');
        if (!$selected_name && count($tracked_names) > 0) {
            $selected_name = $tracked_names[0];
        }

        $player_data = null;
        $inventories = null;
        $storages = null;
        $master_rods = collect();
        $master_fishes = collect();
        $master_mutations = collect();
        $rarity_options = collect();
        $active_view = $request->query('view', 'inventory');
        if (!in_array($active_view, ['inventory', 'storage'], true)) {
            $active_view = 'inventory';
        }

        // Search inputs
        $searchItem     = $request->query('search_item', '');
        $ignore_mutation = $request->query('ignore_mutation') === 'true';
        $rarity_filter  = $request->query('rarity_filter', '');

        if ($selected_name && in_array($selected_name, $tracked_names)) {
            $player_data = Player::with(['rods'])->where('player_name', $selected_name)->first();
            
            if ($player_data) {
                [$master_rods, $master_fishes, $master_mutations, $rarity_options] = $this->loadDashboardLookups();

                if ($active_view === 'inventory') {
                    $invQuery = $player_data->inventories()
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id');
                    if ($searchItem) {
                        $invQuery->where('player_inventories.name', 'like', '%' . $searchItem . '%');
                    }

                    if ($rarity_filter) {
                        $invQuery->join('fishes', 'player_inventories.name', '=', 'fishes.name')
                            ->where('fishes.rarity', $rarity_filter)
                            ->select('player_inventories.*');
                    }

                    if ($ignore_mutation) {
                        $inventories = $this->buildMergedPaginator(
                            $invQuery->get(),
                            $master_fishes,
                            $master_mutations,
                            $request
                        );
                    } else {
                        $inventories = $invQuery->paginate(30)->withQueryString();
                    }
                } else {
                    $storageQuery = $player_data->storages()
                        ->orderByDesc('updated_at')
                        ->orderByDesc('id');
                    if ($searchItem) {
                        $storageQuery->where('player_storages.name', 'like', '%' . $searchItem . '%');
                    }

                    if ($rarity_filter) {
                        $storageQuery->join('fishes', 'player_storages.name', '=', 'fishes.name')
                            ->where('fishes.rarity', $rarity_filter)
                            ->select('player_storages.*');
                    }

                    if ($ignore_mutation) {
                        $storages = $this->buildMergedPaginator(
                            $storageQuery->get(),
                            $master_fishes,
                            $master_mutations,
                            $request
                        );
                    } else {
                        $storages = $storageQuery->paginate(30)->withQueryString();
                    }
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

            $now = now();
            $inventory_items = $this->normalizeItems($json_data['inventory'] ?? []);
            $storage_items = $this->normalizeItems($json_data['storage'] ?? []);
            [$master_fishes, $master_mutations] = $this->loadPricingLookups($inventory_items, $storage_items);

            $player = Player::updateOrCreate(
                ['player_name' => $json_data['playerName']],
                ['coins' => $json_data['coins'] ?? 0]
            );

            $this->syncRods($player, $json_data['rods'] ?? [], $now);

            $inventory_rows_count = 0;
            $inventory_stack_count = 0;
            $inventory_total_value = 0;
            [$inventory_rows_count, $inventory_stack_count, $inventory_total_value] = $this->syncItemSnapshot(
                $player->inventories(),
                $inventory_items,
                PlayerInventory::class,
                $master_fishes,
                $master_mutations,
                $now
            );

            $storage_rows_count = 0;
            $storage_stack_count = 0;
            $storage_total_value = 0;
            [$storage_rows_count, $storage_stack_count, $storage_total_value] = $this->syncItemSnapshot(
                $player->storages(),
                $storage_items,
                PlayerStorage::class,
                $master_fishes,
                $master_mutations,
                $now
            );

            $player->update([
                'coins' => $json_data['coins'] ?? 0,
                'inventory_rows_count' => $inventory_rows_count,
                'inventory_stack_count' => $inventory_stack_count,
                'inventory_total_value' => $inventory_total_value,
                'storage_rows_count' => $storage_rows_count,
                'storage_stack_count' => $storage_stack_count,
                'storage_total_value' => $storage_total_value,
                'updated_at' => $now,
            ]);

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

    protected function buildMergedPaginator(Collection $items, Collection $master_fishes, Collection $master_mutations, Request $request): LengthAwarePaginator
    {
        $merged = $items
            ->groupBy(fn ($item) => trim($item->name))
            ->map(function (Collection $group, string $name) use ($master_fishes, $master_mutations) {
                $total_stack = $group->sum(fn ($item) => max(1, (int) ($item->stack ?? 1)));
                $weighted_weight = $group->sum(fn ($item) => ((float) $item->weight) * max(1, (int) ($item->stack ?? 1)));
                $average_weight = $total_stack > 0 ? $weighted_weight / $total_stack : 0;
                $sell_price = $group->sum(function ($item) use ($master_fishes, $master_mutations) {
                    return $this->calculateSellPrice($item, $master_fishes, $master_mutations);
                });

                return (object) [
                    'name' => $name,
                    'stack' => $total_stack,
                    'weight' => $average_weight,
                    'sparkling' => (bool) $group->max('sparkling'),
                    'shiny' => (bool) $group->max('shiny'),
                    'favourited' => (bool) $group->max('favourited'),
                    'mutation' => null,
                    'merged' => true,
                    'merged_sell_price' => $sell_price,
                    'latest_id' => (int) $group->max('id'),
                ];
            })
            ->sortByDesc('latest_id')
            ->values();

        $per_page = 30;
        $current_page = LengthAwarePaginator::resolveCurrentPage();
        $page_items = $merged->slice(($current_page - 1) * $per_page, $per_page)->values();

        return (new LengthAwarePaginator(
            $page_items,
            $merged->count(),
            $per_page,
            $current_page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        ))->withQueryString();
    }

    protected function calculateSellPrice(object $item, Collection $master_fishes, Collection $master_mutations): int
    {
        $fish_master = $master_fishes->get(trim($item->name));
        if (!$fish_master) {
            return 0;
        }

        $stack_count = max(1, (int) ($item->stack ?? 1));
        $weight_per_item = (float) ($item->weight ?? 0);
        $base_price = (int) ceil(((float) $fish_master->price_per_kg) * $weight_per_item);
        $multiplier = 1.0;

        if (!empty($item->mutation) && $master_mutations->has(trim($item->mutation))) {
            $multiplier *= (float) $master_mutations->get(trim($item->mutation))->multiplier;
        }

        if (!empty($item->shiny)) {
            $multiplier *= 1.85;
        }

        if (!empty($item->sparkling)) {
            $multiplier *= 1.85;
        }

        return (int) ceil($base_price * $multiplier) * $stack_count;
    }

    protected function normalizeItems(array $items): array
    {
        return array_map(function (array $item) {
            return [
                'sparkling' => (bool) ($item['sparkling'] ?? false),
                'name' => $item['name'] ?? '',
                'weight' => (float) ($item['weight'] ?? 0),
                'shiny' => (bool) ($item['shiny'] ?? false),
                'stack' => max(1, (int) ($item['stack'] ?? 1)),
                'mutation' => $item['mutation'] ?? null,
                'favourited' => (bool) ($item['favourited'] ?? false),
            ];
        }, $items);
    }

    protected function loadPricingLookups(array $inventory_items, array $storage_items): array
    {
        $snapshot_items = collect(array_merge($inventory_items, $storage_items));

        $fish_names = $snapshot_items
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();

        $mutation_names = $snapshot_items
            ->pluck('mutation')
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();

        $master_fishes = \App\Models\Fish::query()
            ->when($fish_names->isNotEmpty(), fn ($query) => $query->whereIn('name', $fish_names))
            ->get()
            ->keyBy(fn ($fish) => trim($fish->name));

        $master_mutations = \App\Models\Mutation::query()
            ->when($mutation_names->isNotEmpty(), fn ($query) => $query->whereIn('name', $mutation_names))
            ->get()
            ->keyBy(fn ($mutation) => trim($mutation->name));

        return [$master_fishes, $master_mutations];
    }

    protected function loadDashboardLookups(): array
    {
        $ttl = now()->addMinutes(30);

        $master_rods = Cache::remember('dashboard:master_rods:keyed', $ttl, function () {
            return \App\Models\MasterRod::query()
                ->select([
                    'id',
                    'name',
                    'icon',
                    'image_url',
                    'description',
                    'hint',
                    'from',
                    'strength',
                    'line_distance',
                    'luck',
                    'lure_speed',
                    'resilience',
                    'control',
                    'level_requirement',
                    'disturbance',
                    'mutation_pool',
                    'preferred_disturbance',
                ])
                ->orderBy('name')
                ->get()
                ->keyBy('name');
        });

        $master_fishes = Cache::remember('dashboard:master_fishes:keyed', $ttl, function () {
            return \App\Models\Fish::query()
                ->select(['id', 'name', 'price_per_kg', 'max_weight', 'rarity', 'icon', 'from'])
                ->orderBy('name')
                ->get()
                ->keyBy(fn ($fish) => trim($fish->name));
        });

        $master_mutations = Cache::remember('dashboard:master_mutations:keyed', $ttl, function () {
            return \App\Models\Mutation::query()
                ->select(['id', 'name', 'multiplier'])
                ->orderBy('name')
                ->get()
                ->keyBy(fn ($mutation) => trim($mutation->name));
        });

        $rarity_options = Cache::remember('dashboard:rarity_options', $ttl, function () {
            return \App\Models\Fish::query()
                ->whereNotNull('rarity')
                ->distinct()
                ->orderBy('rarity')
                ->pluck('rarity');
        });

        return [$master_rods, $master_fishes, $master_mutations, $rarity_options];
    }

    protected function syncRods(Player $player, array $rods, $now): void
    {
        $rows = [];
        $sync_keys = [];
        $occurrences = [];

        foreach ($rods as $rod) {
            $normalized = [
                'name' => $rod['name'] ?? $rod['Name'] ?? 'Unknown Rod',
                'icon' => $rod['icon'] ?? $rod['Icon'] ?? '',
            ];
            $sync_key = $this->makeSnapshotSyncKey($normalized, $occurrences);
            $sync_keys[] = $sync_key;
            $rows[] = [
                'player_id' => $player->id,
                'sync_key' => $sync_key,
                'name' => $normalized['name'],
                'icon' => $normalized['icon'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            PlayerRod::upsert($rows, ['player_id', 'sync_key'], ['name', 'icon', 'updated_at']);
            $player->rods()->whereNotIn('sync_key', $sync_keys)->delete();
            return;
        }

        $player->rods()->delete();
    }

    protected function syncItemSnapshot(
        HasMany $relation,
        array $items,
        string $model_class,
        Collection $master_fishes,
        Collection $master_mutations,
        $now
    ): array {
        $player_id = $relation->getParent()->getKey();
        $rows = [];
        $sync_keys = [];
        $occurrences = [];
        $rows_count = 0;
        $stack_count = 0;
        $total_value = 0;

        foreach ($items as $item) {
            $sync_key = $this->makeSnapshotSyncKey($item, $occurrences);
            $sync_keys[] = $sync_key;
            $rows_count++;
            $stack_count += $item['stack'];
            $total_value += $this->calculateSellPrice((object) $item, $master_fishes, $master_mutations);
            $rows[] = [
                'player_id' => $player_id,
                'sync_key' => $sync_key,
                'sparkling' => $item['sparkling'],
                'name' => $item['name'],
                'weight' => $item['weight'],
                'shiny' => $item['shiny'],
                'stack' => $item['stack'],
                'mutation' => $item['mutation'],
                'favourited' => $item['favourited'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                $model_class::upsert(
                    $chunk,
                    ['player_id', 'sync_key'],
                    ['sparkling', 'name', 'weight', 'shiny', 'stack', 'mutation', 'favourited', 'updated_at']
                );
            }

            $relation->whereNotIn('sync_key', $sync_keys)->delete();
        } else {
            $relation->delete();
        }

        return [$rows_count, $stack_count, $total_value];
    }

    protected function makeSnapshotSyncKey(array $attributes, array &$occurrences): string
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

}
