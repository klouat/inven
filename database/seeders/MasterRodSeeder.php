<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MasterRodSeeder extends Seeder
{
    public function run(): void
    {
        $json_path = base_path('../output_with_images.json');

        if (!file_exists($json_path)) {
            $this->command->error("output_with_images.json not found at: {$json_path}");
            return;
        }

        $raw = json_decode(file_get_contents($json_path), true);

        if (!$raw) {
            $this->command->error('Failed to parse output_with_images.json');
            return;
        }

        $insert_data = [];

        foreach ($raw as $rod_name => $data) {
            // Normalize Strength & LineDistance — can be "inf" strings or numbers
            $strength      = $data['Strength'] ?? 0;
            $line_distance = $data['LineDistance'] ?? 0;

            $insert_data[] = [
                'name'                  => $rod_name,
                'icon'                  => $data['Icon'] ?? '',
                'image_url'             => $data['image_url'] ?? null,
                'description'           => $data['Description'] ?? null,
                'hint'                  => $data['Hint'] ?? null,
                'from'                  => $data['From'] ?? null,
                'strength'              => (string) $strength,
                'line_distance'         => (string) $line_distance,
                'luck'                  => (float) ($data['Luck'] ?? 0),
                'lure_speed'            => (float) ($data['LureSpeed'] ?? 0),
                'resilience'            => (float) ($data['Resilience'] ?? 0),
                'control'               => (float) ($data['Control'] ?? 0),
                'level_requirement'     => (int) ($data['LevelRequirement'] ?? 0),
                'disturbance'           => isset($data['Disturbance']) ? (int) $data['Disturbance'] : null,
                'mutation_pool'         => isset($data['MutationPool']) ? json_encode($data['MutationPool']) : null,
                'preferred_disturbance' => isset($data['PreferredDisturbance']) ? json_encode($data['PreferredDisturbance']) : null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
        }

        \App\Models\MasterRod::truncate();

        foreach (array_chunk($insert_data, 200) as $chunk) {
            \App\Models\MasterRod::insert($chunk);
        }

        $this->command->info(count($insert_data) . ' rods seeded from output_with_images.json.');
    }
}
