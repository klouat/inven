<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Models\Fish;
use Exception;

class FishSeeder extends Seeder
{
    public function run(): void
    {
        $file_path = base_path('../fish.json');

        if (!file_exists($file_path)) {
            $this->command->error("fish.json not found at: {$file_path}");
            return;
        }

        try {
            $parsed = json_decode(file_get_contents($file_path), true);

            if (!is_array($parsed)) {
                throw new Exception('Invalid JSON structure. Expected array.');
            }

            Fish::truncate();

            $insert_data = [];
            $seen        = [];

            foreach ($parsed as $item) {
                $name = trim($item['Name'] ?? '');
                if (!$name || isset($seen[$name])) continue;

                $seen[$name]   = true;
                $insert_data[] = [
                    'name'         => $name,
                    'price_per_kg' => (float) ($item['priceperkg'] ?? 0),
                    'max_weight'   => (float) ($item['maxweight'] ?? 0),
                    'rarity'       => $item['Rarity'] ?? null,
                    'icon'         => ($item['Icon'] ?? 'N/A') !== 'N/A' ? $item['Icon'] : null,
                    'from'         => $item['From'] ?? null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            foreach (array_chunk($insert_data, 300) as $chunk) {
                \Illuminate\Support\Facades\DB::table('fishes')->insertOrIgnore($chunk);
            }

            $this->command->info(count($insert_data) . ' fish seeded from fish.json.');

        } catch (Exception $error) {
            Log::error('FishSeeder failed: ' . $error->getMessage());
            throw $error;
        }
    }
}
