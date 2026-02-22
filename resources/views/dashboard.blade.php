<!DOCTYPE html>
<html lang="en" class="bg-neutral-50 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Fisch Analytics</title>
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
                <button type="submit" class="btn px-4 py-2 rounded-sm text-sm font-medium flex items-center justify-center pointer-cursor">
                    Add
                </button>
            </form>

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
        
        <div class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-neutral-200 pb-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight mb-1">{{ $player_data->player_name }}</h2>
                <div class="flex gap-4 text-sm text-neutral-500">
                    <span class="flex items-center gap-1.5 font-medium text-neutral-700">
                        <i data-lucide="coins" class="w-4 h-4 text-neutral-400"></i> {{ number_format($player_data->coins) }}
                    </span>
                    <span class="text-neutral-300">|</span>
                    <span>Last Synced: {{ $player_data->updated_at->diffForHumans() }}</span>
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
                    @endphp
                    <div class="card p-3 flex flex-col items-center gap-3 text-center h-full rod-item" data-name="{{ strtolower($rod->name) }}" data-owned="true">
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
                    <div class="card p-3 flex flex-col items-center gap-3 text-center h-full bg-neutral-50 border-neutral-100 opacity-60 grayscale filter rod-item missing-rod hidden" data-name="{{ strtolower($missing->name) }}" data-owned="false">
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
                <div class="text-xs uppercase font-semibold tracking-wider text-neutral-400 flex items-center gap-2">
                    <i data-lucide="backpack" class="w-3.5 h-3.5"></i> Vault Inventory ({{ $inventories->total() }} items)
                </div>
                <!-- Swapped back to JS-driven instant search instead of form submit -->
                <div class="relative w-full sm:w-64">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="text" id="inv-search-input" value="{{ $searchInv }}" placeholder="Search Fish..." class="w-full pl-9 pr-3 py-1.5 text-sm bg-white focus:bg-white rounded-sm border border-neutral-300">
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
                            <div class="text-sm text-neutral-500 flex items-center gap-1.5 font-medium mb-3">
                                {{ number_format($item->weight, 2) }} kg
                            </div>
                            
                            <div class="mt-auto flex gap-1.5 flex-wrap">
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

    <script>
        lucide.createIcons();

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
                    }, 300); // 300ms debounce
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

        async function fetchInventory(url) {
            const inventoryWrapper = document.getElementById('inventory-wrapper');
            inventoryWrapper.style.opacity = '0.5';
            
            try {
                const response = await fetch(url);
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
                console.error("Failed to fetch inventory", err);
            } finally {
                inventoryWrapper.style.opacity = '1';
            }
        }

        // Initial bind
        attachInventoryListeners();
    </script>
</body>
</html>
