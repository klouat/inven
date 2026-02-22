<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterRodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json_path = 'd:\roblox\fisch\fish_images.json';
        if (!file_exists($json_path)) {
            $this->command->error('fish_images.json not found!');
            return;
        }

        $json_data = json_decode(file_get_contents($json_path), true);
        
        $insertData = [];
        $unique_names = []; // Keep track of unique names to avoid dupes in insert array
        
        foreach ($json_data as $item) {
            $name = $item['Name'] ?? null;
            if (!$name || in_array($name, $unique_names)) continue;
            
            $unique_names[] = $name;
            $insertData[] = [
                'name' => $name,
                'icon' => $item['Icon'] ?? '',
                'image_url' => $item['imageUrl'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \App\Models\MasterRod::truncate();
        
        // Chunk inserts to handle large amounts arrays gracefully
        $chunks = array_chunk($insertData, 500);
        foreach ($chunks as $chunk) {
            \App\Models\MasterRod::insert($chunk);
        }

        $this->command->info(count($insertData) . ' master rods successfully seeded.');
    }
}
