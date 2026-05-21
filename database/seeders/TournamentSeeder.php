<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        Tournament::firstOrCreate(
            ['slug' => 'world-cup-2026'],
            [
                'name' => 'FIFA World Cup 2026',
                'type' => 'world_cup',
                'season' => '2026',
                'logo_url' => null,
                'starts_at' => '2026-06-11',
                'ends_at' => '2026-07-19',
                'is_active' => true,
                'meta' => [
                    'host_countries' => ['USA', 'Canada', 'Mexico'],
                    'teams' => 48,
                    'groups' => 12,
                ],
            ]
        );
    }
}
