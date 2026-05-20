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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
                    $invQuery = $player_data->inventories()->latest('id');
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
                        $inventories = $invQuery->simplePaginate(30)->withQueryString();
                    }
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
                        $storages = $this->buildMergedPaginator(
                            $storageQuery->get(),
                            $master_fishes,
                            $master_mutations,
                            $request
                        );
                    } else {
                        $storages = $storageQuery->simplePaginate(30)->withQueryString();
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

    public function exportFishXlsx(Request $request)
    {
        $user = Auth::user();
        $tracked_names = $user->trackedPlayers->pluck('player_name')->toArray();
        $selected_name = $request->query('player');
        $active_view = $request->query('view', 'inventory');
        $searchItem = $request->query('search_item', '');
        $rarity_filter = $request->query('rarity_filter', '');

        if (!in_array($active_view, ['inventory', 'storage'], true)) {
            $active_view = 'inventory';
        }

        if (!$selected_name || !in_array($selected_name, $tracked_names, true)) {
            abort(404);
        }

        $player = Player::where('player_name', $selected_name)->firstOrFail();
        $master_fishes = \App\Models\Fish::all()->keyBy(fn ($fish) => trim($fish->name));
        $master_mutations = \App\Models\Mutation::all()->keyBy(fn ($mutation) => trim($mutation->name));

        $query = $active_view === 'storage'
            ? $player->storages()->latest('id')
            : $player->inventories()->latest('id');

        $table = $active_view === 'storage' ? 'player_storages' : 'player_inventories';

        if ($searchItem !== '') {
            $query->where($table . '.name', 'like', '%' . $searchItem . '%');
        }

        if ($rarity_filter !== '') {
            $query->join('fishes', $table . '.name', '=', 'fishes.name')
                ->where('fishes.rarity', $rarity_filter)
                ->select($table . '.*');
        }

        $rows = $query->get();

        $export_rows = $rows->map(function ($item) use ($master_fishes, $master_mutations) {
            $fish_master = $master_fishes->get(trim($item->name));

            return [
                'name' => $item->name,
                'rarity' => $fish_master?->rarity ?? '',
                'weight' => (float) ($item->weight ?? 0),
                'stack' => max(1, (int) ($item->stack ?? 1)),
                'price' => $this->calculateSellPrice($item, $master_fishes, $master_mutations),
            ];
        })->values();

        $filename = Str::slug($selected_name) . '-' . $active_view . '-export-' . now()->format('Ymd-His') . '.xlsx';
        $temp_dir = storage_path('app/exports');

        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }

        $temp_path = $temp_dir . DIRECTORY_SEPARATOR . $filename;
        $this->writeSimpleXlsx($temp_path, $export_rows, ucfirst($active_view));

        return response()->download($temp_path, $filename)->deleteFileAfterSend(true);
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

            $master_fishes = \App\Models\Fish::all()->keyBy(fn ($fish) => trim($fish->name));
            $master_mutations = \App\Models\Mutation::all()->keyBy(fn ($mutation) => trim($mutation->name));
            $now = now();

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
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    PlayerRod::insert($rods_data);
                }
            }

            // Batched inventory insertion for optimization
            $player->inventories()->delete();
            $inventory_rows_count = 0;
            $inventory_stack_count = 0;
            $inventory_total_value = 0;
            
            if (!empty($json_data['inventory'])) {
                $inventory_chunks = array_chunk($json_data['inventory'], 500);
                foreach ($inventory_chunks as $chunk) {
                    $inventory_data = [];
                    foreach ($chunk as $item) {
                        $stack = max(1, (int) ($item['stack'] ?? 1));
                        $inventory_rows_count++;
                        $inventory_stack_count += $stack;
                        $inventory_total_value += $this->calculateSellPrice(
                            (object) [
                                'name' => $item['name'] ?? '',
                                'weight' => $item['weight'] ?? 0,
                                'stack' => $stack,
                                'mutation' => $item['mutation'] ?? null,
                                'shiny' => $item['shiny'] ?? false,
                                'sparkling' => $item['sparkling'] ?? false,
                            ],
                            $master_fishes,
                            $master_mutations
                        );

                        $inventory_data[] = [
                            'player_id' => $player->id,
                            'sparkling' => $item['sparkling'] ?? false,
                            'name' => $item['name'],
                            'weight' => $item['weight'] ?? 0,
                            'shiny' => $item['shiny'] ?? false,
                            'stack' => $stack,
                            'mutation' => $item['mutation'] ?? null,
                            'favourited' => $item['favourited'] ?? false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    PlayerInventory::insert($inventory_data);
                }
            }

            // Batched storage insertion mirrors inventory payload handling
            $player->storages()->delete();
            $storage_rows_count = 0;
            $storage_stack_count = 0;
            $storage_total_value = 0;

            if (!empty($json_data['storage'])) {
                $storage_chunks = array_chunk($json_data['storage'], 500);
                foreach ($storage_chunks as $chunk) {
                    $storage_data = [];
                    foreach ($chunk as $item) {
                        $stack = max(1, (int) ($item['stack'] ?? 1));
                        $storage_rows_count++;
                        $storage_stack_count += $stack;
                        $storage_total_value += $this->calculateSellPrice(
                            (object) [
                                'name' => $item['name'] ?? '',
                                'weight' => $item['weight'] ?? 0,
                                'stack' => $stack,
                                'mutation' => $item['mutation'] ?? null,
                                'shiny' => $item['shiny'] ?? false,
                                'sparkling' => $item['sparkling'] ?? false,
                            ],
                            $master_fishes,
                            $master_mutations
                        );

                        $storage_data[] = [
                            'player_id' => $player->id,
                            'sparkling' => $item['sparkling'] ?? false,
                            'name' => $item['name'],
                            'weight' => $item['weight'] ?? 0,
                            'shiny' => $item['shiny'] ?? false,
                            'stack' => $stack,
                            'mutation' => $item['mutation'] ?? null,
                            'favourited' => $item['favourited'] ?? false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    PlayerStorage::insert($storage_data);
                }
            }

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

    protected function writeSimpleXlsx(string $file_path, Collection $rows, string $sheet_name): void
    {
        $headers = ['Name', 'Rarity', 'Weight', 'Stack', 'Price'];
        $sheet_rows = [$headers];

        foreach ($rows as $row) {
            $sheet_rows[] = [
                (string) $row['name'],
                (string) $row['rarity'],
                (float) $row['weight'],
                (int) $row['stack'],
                (int) $row['price'],
            ];
        }

        $sheet_xml = $this->buildWorksheetXml($sheet_rows);
        $workbook_name = $this->xmlEscape(mb_substr($sheet_name, 0, 31));

        $files = [
            '[Content_Types].xml' => $this->buildContentTypesXml(),
            '_rels/.rels' => $this->buildRootRelsXml(),
            'xl/workbook.xml' => $this->buildWorkbookXml($workbook_name),
            'xl/styles.xml' => $this->buildStylesXml(),
            'xl/_rels/workbook.xml.rels' => $this->buildWorkbookRelsXml(),
            'xl/worksheets/sheet1.xml' => $sheet_xml,
        ];

        if (!class_exists(\ZipArchive::class)) {
            throw new Exception('XLSX export requires the PHP zip extension. Enable `zip` in Laragon PHP extensions, then restart Laragon.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Unable to create XLSX export.');
        }

        foreach ($files as $relative_path => $contents) {
            $zip->addFromString($relative_path, $contents);
        }

        $zip->close();
    }

    protected function buildWorksheetXml(array $rows): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml[] = '<sheetData>';

        foreach ($rows as $row_index => $row) {
            $excel_row = $row_index + 1;
            $xml[] = '<row r="' . $excel_row . '">';

            foreach ($row as $column_index => $value) {
                $cell_ref = $this->columnLetter($column_index + 1) . $excel_row;
                if ($row_index === 0 || is_string($value)) {
                    $xml[] = '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $this->xmlEscape((string) $value) . '</t></is></c>';
                } else {
                    $xml[] = '<c r="' . $cell_ref . '"><v>' . $value . '</v></c>';
                }
            }

            $xml[] = '</row>';
        }

        $xml[] = '</sheetData>';
        $xml[] = '</worksheet>';

        return implode('', $xml);
    }

    protected function buildContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    protected function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function buildWorkbookXml(string $sheet_name): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="' . $sheet_name . '" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    protected function buildWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    protected function columnLetter(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letters = chr(65 + $modulo) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

}
