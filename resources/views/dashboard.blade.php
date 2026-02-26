<!DOCTYPE html>
<html lang="en" class="bg-neutral-50 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Fisch Analytics</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('icon.ico') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #171717; /* neutral-900 */
        }
        
        .panel {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
        }

        .btn {
            background-color: #171717;
            color: #ffffff;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #404040;
        }

        input, select {
            border: 1px solid #d4d4d4;
            transition: border-color 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #a3a3a3;
        }

        .card {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
            transition: border-color 0.2s;
        }

        .card:hover {
            border-color: #a3a3a3;
        }

        .badge {
            background-color: #f5f5f5;
            border: 1px solid #e5e5e5;
            color: #525252;
        }
        
        /* Laravel Tailwing Pagination Fixes for CDN */
        nav[role="navigation"] > div {
            margin-top: 1rem;
        }

        /* Rod Modal */
        #rod-modal {
            transition: opacity 0.2s ease;
        }
        #rod-modal.hidden {
            display: none;
        }
        #rod-modal-panel {
            animation: slide-up 0.2s ease;
        }
        @keyframes slide-up {
            from { transform: translateY(12px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #f5f5f5;
            font-size: 0.8125rem;
        }
        .stat-row:last-child { border-bottom: none; }
        .stat-label { color: #737373; font-weight: 500; }
        .stat-value { font-weight: 600; color: #171717; }
    </style>
</head>
<body class="p-4 md:p-8 lg:p-12 pb-24 max-w-7xl mx-auto">

    <!-- Header Navigation -->
    <header class="flex flex-col lg:flex-row justify-between items-start lg:items-center border-b border-neutral-200 pb-8 mb-8 gap-6">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 mb-1">Fisch Database</h1>
            <p class="text-neutral-500 text-sm">
                Signed in as <span class="font-medium text-neutral-800">{{ Auth::user()->name }}</span>
            </p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto items-stretch sm:items-center">
            
            <form action="{{ route('track.player') }}" method="POST" class="flex w-full sm:w-auto gap-2">
                @csrf
                <input type="text" name="player_name" placeholder="Track a Username..." required class="px-4 py-2 rounded-sm w-full text-sm">
                <button type="submit" class="btn px-4 py-2 rounded-sm text-sm font-medium flex items-center justify-center pointer-cursor cursor-pointer">
                    Add
                </button>
            </form>

            <button type="button" onclick="copyGetScript()" class="btn px-4 py-2 rounded-sm text-sm font-medium flex items-center justify-center cursor-pointer ml-0 sm:ml-2">
                <i data-lucide="key" class="w-4 h-4 mr-2"></i> Get Key
            </button>

            <form action="{{ route('logout') }}" method="POST" class="ml-auto sm:ml-2">
                @csrf
                <button type="submit" class="text-sm font-medium text-neutral-500 hover:text-red-600 transition-colors border-b border-transparent hover:border-red-600 pb-0.5 cursor-pointer">
                    Sign Out
                </button>
            </form>
        </div>
    </header>

    {{-- System Alerts --}}
    @if(session('success'))
        <div class="bg-green-50 text-green-800 border-l-4 border-green-600 p-4 mb-8 text-sm flex items-center gap-3">
            <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
         <div class="bg-red-50 text-red-800 border-l-4 border-red-600 p-4 mb-8 text-sm flex items-center gap-3">
            <i data-lucide="alert-circle" class="w-4 h-4 text-red-600"></i>
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 text-red-800 border-l-4 border-red-600 p-4 mb-8 text-sm flex items-start gap-3">
            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 text-red-600"></i>
            <ul class="list-disc ml-4 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tracked Players Selector --}}
    @if($tracked_players->count() > 0)
        <div class="mb-10">
            <div class="text-xs uppercase font-semibold tracking-wider text-neutral-400 mb-3">Viewing Profiles</div>
            <div class="flex flex-wrap gap-2">
                @foreach($tracked_players as $tp)
                    <div class="flex items-center">
                        <a href="{{ route('home', ['player' => $tp->player_name]) }}" 
                           class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition-colors border 
                           @if($selected_name === $tp->player_name) bg-black text-white border-black hover:bg-neutral-800 
                           @else bg-white text-neutral-600 border-neutral-200 hover:border-neutral-400 @endif">
                            <i data-lucide="user" class="w-3.5 h-3.5"></i>
                            {{ $tp->player_name }}
                        </a>
                        <form action="{{ route('untrack.player', $tp->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2 py-1.5 h-full text-neutral-400 border border-l-0 border-neutral-200 bg-white hover:text-red-500 hover:bg-red-50 transition-colors cursor-pointer" title="Untrack">
                                <i data-lucide="x" class="w-3.5 h-3.5 mt-[2px]"></i>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Empty States --}}
    @if($tracked_players->count() === 0)
        <div class="panel p-12 text-center mt-8 text-neutral-500">
            <p class="text-lg text-neutral-800 font-medium mb-1">No profiles tracked</p>
            <p class="text-sm">Please add a username to your watch list using the input above.</p>
        </div>
    @elseif(!$player_data && $selected_name)
        <div class="panel p-12 text-center mt-8 text-neutral-500 flex flex-col items-center">
            <i data-lucide="database-zap" class="w-8 h-8 opacity-40 mb-3"></i>
            <p class="text-lg text-neutral-800 font-medium mb-1">No JSON data found</p>
            <p class="text-sm">We are tracking <strong>{{ $selected_name }}</strong>, but their data has never been synced to the database.</p>
            <p class="text-sm mt-1">Upload their payload using the JSON upload button.</p>
        </div>
    @elseif($player_data)
        
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-200 pb-6">
            <div>
                <h2 class="text-3xl font-bold tracking-tight mb-1 text-neutral-900">{{ $player_data->player_name }}</h2>
                <div class="flex gap-4 text-sm text-neutral-500">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-4 h-4 text-neutral-400"></i>
                        Last Synced: {{ $player_data->updated_at->diffForHumans() }}
                    </span>
                </div>
            </div>
            
            <div class="flex flex-row gap-3 sm:gap-4 overflow-x-auto pb-2 md:pb-0">
                <div class="card p-3 sm:p-4 rounded-xl flex flex-col min-w-[140px] sm:min-w-[170px] border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 shadow-sm relative overflow-hidden">
                    <i data-lucide="coins" class="absolute -right-2 -bottom-2 w-16 h-16 text-amber-500 opacity-10"></i>
                    <span class="text-amber-700 text-[11px] sm:text-xs uppercase font-bold tracking-wider mb-1 flex items-center gap-1.5 relative z-10">
                        <i data-lucide="coins" class="w-4 h-4 text-amber-500"></i> Player Coins
                    </span>
                    <span class="text-xl sm:text-2xl font-extrabold text-amber-900 relative z-10">{{ number_format($player_data->coins) }}</span>
                </div>
                
                <div class="card p-3 sm:p-4 rounded-xl flex flex-col min-w-[140px] sm:min-w-[170px] border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 shadow-sm relative overflow-hidden">
                    <i data-lucide="banknote" class="absolute -right-2 -bottom-2 w-16 h-16 text-green-500 opacity-10"></i>
                    <span class="text-green-700 text-[11px] sm:text-xs uppercase font-bold tracking-wider mb-1 flex items-center gap-1.5 relative z-10">
                        <i data-lucide="banknote" class="w-4 h-4 text-green-500"></i> Total Value
                    </span>
                    <span class="text-xl sm:text-2xl font-extrabold text-green-900 relative z-10">{{ number_format($total_sell_value) }}</span>
                </div>
            </div>
        </div>

        {{-- RODS SECTION (DOM Filtered) --}}
        <section class="mb-12">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-4">
                <div class="flex flex-col gap-1">
                    <div class="text-xs uppercase font-semibold tracking-wider text-neutral-400 flex items-center gap-2">
                        <i data-lucide="anchor" class="w-3.5 h-3.5"></i> Equipment (Rods: {{ $player_data->rods->count() }} / {{ $master_rods->count() }})
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer w-max mt-1">
                        <input type="checkbox" id="toggle-unowned"> Show Missing Rods
                    </label>
                </div>
                
                <div class="relative w-full sm:w-64">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="text" id="rod-search-input" placeholder="Search Rods..." class="w-full pl-9 pr-3 py-1.5 text-sm bg-white focus:bg-white rounded-sm border border-neutral-300">
                </div>
            </div>
            
            @php
                $owned_rod_names = collect($player_data->rods)->pluck('name')->toArray();
                
                $missing_rods = $master_rods->filter(function($m) use ($owned_rod_names) {
                    return !in_array($m->name, $owned_rod_names);
                });
            @endphp

            @if($player_data->rods->isEmpty() && $missing_rods->isEmpty())
                <div class="text-sm text-neutral-500 italic">No rods exist in database.</div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3" id="rods-grid">
                    
                    {{-- OWNED --}}
                    @foreach($player_data->rods as $rod)
                    @php
                        $master = $master_rods[$rod->name] ?? null;
                        $rod_data = $master ? json_encode([
                            'name'                 => $master->name,
                            'image_url'            => $master->image_url,
                            'description'          => $master->description,
                            'strength'             => $master->strength,
                            'line_distance'        => $master->line_distance,
                            'luck'                 => $master->luck,
                            'lure_speed'           => $master->lure_speed,
                            'resilience'           => $master->resilience,
                            'control'              => $master->control,
                            'level_requirement'    => $master->level_requirement,
                            'disturbance'          => $master->disturbance,
                            'mutation_pool'        => $master->mutation_pool,
                            'preferred_disturbance'=> $master->preferred_disturbance,
                            'from'                 => $master->from,
                            'hint'                 => $master->hint,
                            'owned'                => true,
                        ]) : '{}';
                    @endphp
                    <div class="card p-3 flex flex-col items-center gap-3 text-center h-full rod-item cursor-pointer hover:border-neutral-400 transition-colors"
                         data-name="{{ strtolower($rod->name) }}"
                         data-owned="true"
                         onclick="openRodModal({{ Js::from(json_decode($rod_data)) }})">
                        <div class="h-16 w-16 bg-neutral-100 flex items-center justify-center p-1 rounded-sm border border-neutral-200">
                            @if($master && $master->image_url)
                                <img src="{{ $master->image_url }}" alt="{{ $rod->name }}" class="object-contain w-full h-full drop-shadow-sm">
                            @else
                                <i data-lucide="ruler" class="w-6 h-6 text-neutral-400"></i>
                            @endif
                        </div>
                        <span class="text-xs font-semibold text-neutral-800 leading-tight">{{ $rod->name }}</span>
                    </div>
                    @endforeach

                    {{-- MISSING (Unowned) --}}
                    @foreach($missing_rods as $missing)
                    @php
                        $miss_data = json_encode([
                            'name'                 => $missing->name,
                            'image_url'            => $missing->image_url,
                            'description'          => $missing->description,
                            'strength'             => $missing->strength,
                            'line_distance'        => $missing->line_distance,
                            'luck'                 => $missing->luck,
                            'lure_speed'           => $missing->lure_speed,
                            'resilience'           => $missing->resilience,
                            'control'              => $missing->control,
                            'level_requirement'    => $missing->level_requirement,
                            'disturbance'          => $missing->disturbance,
                            'mutation_pool'        => $missing->mutation_pool,
                            'preferred_disturbance'=> $missing->preferred_disturbance,
                            'from'                 => $missing->from,
                            'hint'                 => $missing->hint,
                            'owned'                => false,
                        ]);
                    @endphp
                    <div class="card p-3 flex flex-col items-center gap-3 text-center h-full bg-neutral-50 border-neutral-100 opacity-60 grayscale filter rod-item missing-rod hidden cursor-pointer hover:opacity-80 transition-opacity"
                         data-name="{{ strtolower($missing->name) }}"
                         data-owned="false"
                         onclick="openRodModal({{ Js::from(json_decode($miss_data)) }})">
                        <div class="h-16 w-16 flex items-center justify-center p-1 rounded-sm border border-neutral-200">
                            @if($missing->image_url)
                                <img src="{{ $missing->image_url }}" alt="{{ $missing->name }}" class="object-contain w-full h-full">
                            @else
                                <i data-lucide="ruler" class="w-6 h-6 text-neutral-300"></i>
                            @endif
                        </div>
                        <span class="text-xs font-semibold text-neutral-500 leading-tight line-through">{{ $missing->name }}</span>
                    </div>
                    @endforeach

                </div>
            @endif
        </section>

        {{-- INVENTORY SECTION (Paginated) --}}
        <section id="inventory-wrapper" class="transition-opacity duration-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-4">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="text-xs uppercase font-semibold tracking-wider text-neutral-400 flex items-center gap-2">
                        <i data-lucide="backpack" class="w-3.5 h-3.5"></i> Vault Inventory ({{ $inventories->total() }} items)
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer w-max">
                        <input type="checkbox" id="inv-ignore-mutation" {{ isset($ignore_mutation) && $ignore_mutation ? 'checked' : '' }}> Ignore Mutation & Weight (Merge)
                    </label>
                </div>
                <!-- Swapped back to JS-driven instant search instead of form submit -->
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <select id="inv-rarity-filter" class="pl-3 pr-8 py-1.5 text-sm bg-white rounded-sm border border-neutral-300 cursor-pointer">
                        <option value="">All Rarities</option>
                        @foreach($rarity_options as $rarity)
                            <option value="{{ $rarity }}" {{ $rarity_filter === $rarity ? 'selected' : '' }}>{{ $rarity }}</option>
                        @endforeach
                    </select>
                    <div class="relative w-full sm:w-48">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                        <input type="text" id="inv-search-input" value="{{ $searchInv }}" placeholder="Search Fish..." class="w-full pl-9 pr-3 py-1.5 text-sm bg-white focus:bg-white rounded-sm border border-neutral-300">
                    </div>
                </div>
            </div>
            
            @if($inventories->isEmpty())
                <div class="text-sm text-neutral-500 italic panel p-6 text-center">Backpack is empty or no match found for '{{ $searchInv }}'.</div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    @foreach($inventories as $item)
                        <div class="card p-4 flex flex-col h-full group">
                            
                            <div class="flex justify-between items-start mb-3">
                                <div class="bg-neutral-100 text-neutral-500 w-10 h-10 flex items-center justify-center transition-colors group-hover:bg-neutral-800 group-hover:text-white rounded-sm">
                                    <i data-lucide="fish" class="w-5 h-5"></i>
                                </div>
                                @if($item->stack > 1)
                                    <div class="badge px-2 py-0.5 text-xs font-semibold rounded-sm">
                                        x{{ $item->stack }}
                                    </div>
                                @endif
                            </div>
                            
                            <h4 class="font-semibold text-neutral-900 mb-[2px] truncate" title="{{ $item->name }}">{{ $item->name }}</h4>
                            <div class="text-sm text-neutral-500 flex items-center gap-1.5 font-medium mb-1">
                                {{ number_format($item->weight, 2) }} kg
                            </div>

                            @php
                                $fish_master = $master_fishes[trim($item->name)] ?? null;
                                $classification = 'Normal';
                                $sell_price = 0;
                                $rc = 'bg-neutral-100 text-neutral-500 border-neutral-200';

                                $rarity_colors = [
                                    'Trash'     => 'bg-stone-100 text-stone-500 border-stone-200',
                                    'Common'    => 'bg-neutral-100 text-neutral-600 border-neutral-200',
                                    'Uncommon'  => 'bg-green-50 text-green-700 border-green-200',
                                    'Unusual'   => 'bg-teal-50 text-teal-700 border-teal-200',
                                    'Rare'      => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'Legendary' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                    'Mythical'  => 'bg-purple-50 text-purple-700 border-purple-200',
                                    'Secret'    => 'bg-red-50 text-red-700 border-red-200',
                                    'Exotic'    => 'bg-orange-50 text-orange-700 border-orange-200',
                                    'Limited'   => 'bg-pink-50 text-pink-700 border-pink-200',
                                    'Extinct'   => 'bg-lime-50 text-lime-700 border-lime-200',
                                    'Apex'      => 'bg-rose-50 text-rose-700 border-rose-200',
                                    'Fragment'  => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                    'Special'   => 'bg-violet-50 text-violet-700 border-violet-200',
                                    'Relic'     => 'bg-amber-50 text-amber-700 border-amber-200',
                                ];
                                if ($fish_master) {
                                    $rc = $rarity_colors[$fish_master->rarity] ?? $rc;
                                }

                                $current_weight = $item->weight;
                                $stack_count = max(1, $item->stack ?? 1);
                                $weight_per_item = $current_weight / $stack_count;

                                if ($fish_master && $fish_master->max_weight > 0) {
                                    $max_weight_in_kg = $fish_master->max_weight / 10;
                                    $ratio = $weight_per_item / $max_weight_in_kg;
                                    if ($ratio >= 1.99) {
                                        $classification = 'Giant';
                                    } elseif ($ratio > 1.0) {
                                        $classification = 'Big';
                                    }
                                }

                                if ($fish_master) {
                                    $base_price = ceil($fish_master->price_per_kg * $weight_per_item);
                                    $multiplier = 1.0;

                                    if ($item->mutation && isset($master_mutations[$item->mutation])) {
                                        $multiplier *= (float)$master_mutations[$item->mutation]->multiplier;
                                    }

                                    if ($item->shiny) {
                                        $multiplier *= 1.85;
                                    }

                                    if ($item->sparkling) {
                                        $multiplier *= 1.85;
                                    }

                                    if ($classification === 'Giant') {
                                        $multiplier *= 2.0;
                                    }

                                    $price_per_item = ceil($base_price * $multiplier);
                                    $sell_price = $price_per_item * $stack_count;
                                }
                            @endphp
                            
                            <div class="mt-auto flex gap-1.5 flex-wrap">
                                @if($fish_master && $fish_master->rarity)
                                    <span class="text-[10px] font-bold uppercase tracking-wide border px-1.5 py-0.5 rounded-sm {{ $rc }}">{{ $fish_master->rarity }}</span>
                                @endif
                                @if($sell_price > 0)
                                    <span class="text-[10px] uppercase font-bold text-green-700 border border-green-300 bg-green-50 px-1.5 py-0.5 rounded-sm flex items-center gap-1">
                                        <i data-lucide="coins" class="w-3 h-3 text-green-600"></i> {{ number_format($sell_price) }}
                                    </span>
                                @endif
                                @if($classification === 'Giant')
                                    <span class="text-[10px] uppercase font-bold text-amber-600 border border-amber-300 bg-amber-50 px-1.5 py-0.5 rounded-sm">Giant</span>
                                @elseif($classification === 'Big')
                                    <span class="text-[10px] uppercase font-bold text-indigo-600 border border-indigo-300 bg-indigo-50 px-1.5 py-0.5 rounded-sm">Big</span>
                                @endif
                                
                                @if($item->sparkling)
                                    <span class="text-[10px] uppercase font-bold text-neutral-600 border border-neutral-300 bg-white px-1.5 py-0.5 rounded-sm">Sparkling</span>
                                @endif
                                @if($item->shiny)
                                    <span class="text-[10px] uppercase font-bold text-neutral-600 border border-neutral-300 bg-white px-1.5 py-0.5 rounded-sm">Shiny</span>
                                @endif
                                @if($item->mutation)
                                    <span class="text-[10px] uppercase font-bold text-neutral-600 border border-neutral-300 bg-white px-1.5 py-0.5 rounded-sm">{{ $item->mutation }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                
                {{-- Pagination Links --}}
                <div class="mt-8">
                    {{ $inventories->links() }}
                </div>
            @endif
        </section>

    @endif

    {{-- Rod Detail Modal --}}
    <div id="rod-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.45);backdrop-filter:blur(2px)">
        <div id="rod-modal-panel" class="panel rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl">
            {{-- Modal Header --}}
            <div class="flex items-start gap-4 p-5 border-b border-neutral-100">
                <div class="w-20 h-20 flex-shrink-0 bg-neutral-100 rounded-lg border border-neutral-200 flex items-center justify-center overflow-hidden">
                    <img id="modal-img" src="" alt="" class="w-full h-full object-contain drop-shadow-sm">
                </div>
                <div class="flex-1 min-w-0">
                    <div id="modal-owned-badge" class="inline-block text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-sm mb-1.5"></div>
                    <h3 id="modal-name" class="text-lg font-bold text-neutral-900 leading-tight"></h3>
                    <p id="modal-from" class="text-xs text-neutral-500 mt-0.5"></p>
                </div>
                <button onclick="closeRodModal()" class="text-neutral-400 hover:text-neutral-700 transition-colors cursor-pointer flex-shrink-0 mt-0.5">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Description --}}
            <div class="px-5 pt-4 pb-2">
                <p id="modal-description" class="text-sm text-neutral-600 leading-relaxed whitespace-pre-line"></p>
            </div>

            {{-- Stats --}}
            <div class="px-5 pb-2">
                <div class="text-[10px] uppercase font-semibold tracking-wider text-neutral-400 mb-2 mt-3">Stats</div>
                <div id="modal-stats" class="bg-neutral-50 rounded-lg px-4 py-1 border border-neutral-100"></div>
            </div>

            {{-- Mutation Pool --}}
            <div id="modal-mutation-section" class="px-5 pb-2">
                <div class="text-[10px] uppercase font-semibold tracking-wider text-neutral-400 mb-2 mt-3">Mutation Pool</div>
                <div id="modal-mutations" class="flex flex-wrap gap-1.5"></div>
            </div>

            {{-- Preferred Disturbance --}}
            <div id="modal-disturbance-section" class="px-5 pb-2 hidden">
                <div class="text-[10px] uppercase font-semibold tracking-wider text-neutral-400 mb-2 mt-3">Preferred Disturbance</div>
                <div id="modal-disturbance" class="text-sm text-neutral-700"></div>
            </div>

            {{-- Hint --}}
            <div class="px-5 pt-2 pb-5">
                <div class="text-[10px] uppercase font-semibold tracking-wider text-neutral-400 mb-1.5 mt-1">Hint</div>
                <p id="modal-hint" class="text-sm text-neutral-500 italic"></p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function copyGetScript() {
            const scriptStr = `loadstring(game:HttpGet("https://vss.pandadevelopment.net/virtual/file/45e044b7480e4144"))()`;
            navigator.clipboard.writeText(scriptStr).then(() => {
                alert('Script copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        // 1. Local JS Filtering for Rods
        const toggleBtn = document.getElementById('toggle-unowned');
        const rodSearchInput = document.getElementById('rod-search-input');
        const rodItems = document.querySelectorAll('.rod-item');

        function filterRods() {
            if (!rodSearchInput) return;
            const term = rodSearchInput.value.toLowerCase();
            const showMissing = toggleBtn && toggleBtn.checked;

            rodItems.forEach(item => {
                const name = item.dataset.name;
                const owned = item.dataset.owned === 'true';
                
                let matchesSearch = name.includes(term);
                let matchesToggle = owned || showMissing;

                if (matchesSearch && matchesToggle) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }

        if (rodSearchInput) rodSearchInput.addEventListener('input', filterRods);
        if (toggleBtn) toggleBtn.addEventListener('change', filterRods);

        // 2. AJAX Fetch for Inventory (Pagination & Search)
        function attachInventoryListeners() {
            const inventoryWrapper = document.getElementById('inventory-wrapper');
            if(!inventoryWrapper) return;

            // Search input
            const invSearch = document.getElementById('inv-search-input');
            const ignoreMutationCb = document.getElementById('inv-ignore-mutation');
            
            if (ignoreMutationCb && !ignoreMutationCb.dataset.bound) {
                ignoreMutationCb.dataset.bound = "true";
                ignoreMutationCb.addEventListener('change', (e) => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('ignore_mutation', e.target.checked);
                    url.searchParams.delete('page');
                    fetchInventory(url.toString());
                });
            }

            const raritySelect = document.getElementById('inv-rarity-filter');
            if (raritySelect && !raritySelect.dataset.bound) {
                raritySelect.dataset.bound = "true";
                raritySelect.addEventListener('change', (e) => {
                    const url = new URL(window.location.href);
                    if (e.target.value) {
                        url.searchParams.set('rarity_filter', e.target.value);
                    } else {
                        url.searchParams.delete('rarity_filter');
                    }
                    url.searchParams.delete('page');
                    fetchInventory(url.toString());
                });
            }

            if (invSearch && !invSearch.dataset.bound) {
                invSearch.dataset.bound = "true";
                let timeout = null;
                invSearch.addEventListener('input', (e) => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        const val = e.target.value;
                        const url = new URL(window.location.href);
                        url.searchParams.set('search_inv', val);
                        url.searchParams.delete('page'); // Reset to page 1 on search
                        fetchInventory(url.toString());
                    }, 500); // 500ms debounce
                });
            }

            // Pagination Links
            const paginationLinks = inventoryWrapper.querySelectorAll('nav a');
            paginationLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    fetchInventory(link.href);
                });
            });
        }

        let currentFetchController = null;

        async function fetchInventory(url) {
            const inventoryWrapper = document.getElementById('inventory-wrapper');
            inventoryWrapper.style.opacity = '0.5';
            
            if (currentFetchController) {
                currentFetchController.abort();
            }
            currentFetchController = new AbortController();
            const signal = currentFetchController.signal;
            
            try {
                const response = await fetch(url, { signal });
                const html = await response.text();
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('inventory-wrapper');
                
                if (newContent) {
                    inventoryWrapper.innerHTML = newContent.innerHTML;
                    lucide.createIcons(); // Reactivate icons in new DOM elements
                    attachInventoryListeners(); // Rebind events to new pagination & search
                    window.history.pushState({}, '', url); // Update URL dynamically
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error("Failed to fetch inventory", err);
                }
            } finally {
                if (!signal.aborted) {
                    inventoryWrapper.style.opacity = '1';
                }
            }
        }

        // Initial bind
        attachInventoryListeners();

        // --- Rod Modal ---
        function openRodModal(rod) {
            const modal = document.getElementById('rod-modal');

            document.getElementById('modal-img').src         = rod.image_url || '';
            document.getElementById('modal-img').alt         = rod.name;
            document.getElementById('modal-name').textContent = rod.name;
            document.getElementById('modal-from').textContent = rod.from ? '📍 ' + rod.from : '';
            document.getElementById('modal-description').textContent = rod.description || 'No description.';
            document.getElementById('modal-hint').textContent = rod.hint || 'N/A';

            // Owned badge
            const badge = document.getElementById('modal-owned-badge');
            if (rod.owned) {
                badge.textContent = '✓ Owned';
                badge.className = 'inline-block text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-sm mb-1.5 bg-green-100 text-green-700 border border-green-200';
            } else {
                badge.textContent = '✕ Not Owned';
                badge.className = 'inline-block text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-sm mb-1.5 bg-neutral-100 text-neutral-500 border border-neutral-200';
            }

            // Stats
            const stats = [
                { label: 'Strength',          value: rod.strength ?? '—' },
                { label: 'Line Distance',     value: rod.line_distance ?? '—' },
                { label: 'Luck',              value: rod.luck ?? 0 },
                { label: 'Lure Speed',        value: rod.lure_speed ?? 0 },
                { label: 'Resilience',        value: rod.resilience ?? 0 },
                { label: 'Control',           value: rod.control ?? 0 },
                { label: 'Level Requirement', value: rod.level_requirement ?? 0 },
                { label: 'Disturbance',       value: rod.disturbance ?? '—' },
            ];
            document.getElementById('modal-stats').innerHTML = stats.map(s =>
                `<div class="stat-row"><span class="stat-label">${s.label}</span><span class="stat-value">${s.value}</span></div>`
            ).join('');

            // Mutation pool
            const mutSection = document.getElementById('modal-mutation-section');
            const mutContainer = document.getElementById('modal-mutations');
            const pool = rod.mutation_pool;
            if (pool && typeof pool === 'object' && Object.keys(pool).length > 0) {
                mutSection.classList.remove('hidden');
                mutContainer.innerHTML = Object.entries(pool).map(([name, chance]) =>
                    `<span class="text-[11px] font-semibold border border-neutral-200 bg-white px-2 py-0.5 rounded-sm text-neutral-700">${name} <span class="text-neutral-400">${chance}%</span></span>`
                ).join('');
            } else {
                mutSection.classList.add('hidden');
            }

            // Preferred disturbance
            const distSection = document.getElementById('modal-disturbance-section');
            const distEl = document.getElementById('modal-disturbance');
            if (rod.preferred_disturbance && rod.preferred_disturbance.Event) {
                distSection.classList.remove('hidden');
                distEl.textContent = rod.preferred_disturbance.Event + ' (Risk: ' + (rod.preferred_disturbance.Risk ?? '?') + ')';
            } else {
                distSection.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            lucide.createIcons();
        }

        function closeRodModal() {
            document.getElementById('rod-modal').classList.add('hidden');
        }

        // Close on backdrop click
        document.getElementById('rod-modal').addEventListener('click', function(e) {
            if (e.target === this) closeRodModal();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeRodModal();
        });
    </script>
</body>
</html>
