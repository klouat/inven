<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Fish;
use Exception;

class FishSeeder extends Seeder
{
    public function run(): void
    {
        $file_path = base_path('price_per_kg.json');

        if (!File::exists($file_path)) {
            Log::error("Seeder failed: $file_path not found.");
            return;
        }

        try {
            $json_content = File::get($file_path);
            $parsed_json = json_decode($json_content, true);

            if (!is_array($parsed_json)) {
                throw new Exception('Invalid JSON structure. Expected array.');
            }

            foreach ($parsed_json as $item) {
                if (!isset($item['name'])) continue;

                Fish::updateOrCreate(
                    ['name' => $item['name']],
                    [
                        'price_per_kg' => isset($item['priceperkg']) ? (float)$item['priceperkg'] : 0,
                        'max_weight' => isset($item['maxweight']) ? (float)$item['maxweight'] : 0,
                    ]
                );
            }
        } catch (Exception $error) {
            Log::error('Failed to parse or seed fish data: ' . $error->getMessage());
            throw $error;
        }
    }
}
