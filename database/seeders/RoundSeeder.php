<?php

namespace Database\Seeders;

use App\Models\Round;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class RoundSeeder extends Seeder
{
    public function run(): void
    {
        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();

        $rounds = [
            // 12 groups
            ['name' => 'Group A', 'type' => 'group', 'order' => 1],
            ['name' => 'Group B', 'type' => 'group', 'order' => 2],
            ['name' => 'Group C', 'type' => 'group', 'order' => 3],
            ['name' => 'Group D', 'type' => 'group', 'order' => 4],
            ['name' => 'Group E', 'type' => 'group', 'order' => 5],
            ['name' => 'Group F', 'type' => 'group', 'order' => 6],
            ['name' => 'Group G', 'type' => 'group', 'order' => 7],
            ['name' => 'Group H', 'type' => 'group', 'order' => 8],
            ['name' => 'Group I', 'type' => 'group', 'order' => 9],
            ['name' => 'Group J', 'type' => 'group', 'order' => 10],
            ['name' => 'Group K', 'type' => 'group', 'order' => 11],
            ['name' => 'Group L', 'type' => 'group', 'order' => 12],
            // Knockout
            ['name' => 'Round of 32', 'type' => 'round_of_32', 'order' => 13],
            ['name' => 'Round of 16', 'type' => 'round_of_16', 'order' => 14],
            ['name' => 'Quarter-finals', 'type' => 'quarter', 'order' => 15],
            ['name' => 'Semi-finals', 'type' => 'semi', 'order' => 16],
            ['name' => 'Third Place', 'type' => 'third_place', 'order' => 17],
            ['name' => 'Final', 'type' => 'final', 'order' => 18],
        ];

        foreach ($rounds as $round) {
            Round::firstOrCreate(
                ['tournament_id' => $tournament->id, 'name' => $round['name']],
                ['type' => $round['type'], 'order' => $round['order']]
            );
        }
    }
}
