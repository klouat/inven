<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Mutation;
use Exception;

class MutationSeeder extends Seeder
{
    public function run(): void
    {
        $file_path = base_path('mutation.json');

        if (!File::exists($file_path)) {
            Log::error("Seeder failed: $file_path not found.");
            return;
        }

        try {
            $json_content = File::get($file_path);
            $parsed_json = json_decode($json_content, true);

            if (!is_array($parsed_json)) {
                throw new Exception('Invalid JSON structure. Expected array/object.');
            }

            foreach ($parsed_json as $key => $item) {
                $name = $key;
                $multiplier = isset($item['PriceMultiplier']) ? (float)$item['PriceMultiplier'] : 1.0;

                Mutation::updateOrCreate(
                    ['name' => $name],
                    ['multiplier' => $multiplier]
                );
            }
        } catch (Exception $error) {
            Log::error('Failed to parse or seed mutations: ' . $error->getMessage());
            throw $error;
        }
    }
}
