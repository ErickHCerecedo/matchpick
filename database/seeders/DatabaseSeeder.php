<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            TeamSeeder::class,
            TournamentSeeder::class,
            RoundSeeder::class,
            MatchSeeder::class,
        ]);
    }
}
