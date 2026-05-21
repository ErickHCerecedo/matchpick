<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $countries = Country::all()->keyBy('iso_code');

        foreach ($countries as $country) {
            Team::firstOrCreate(
                ['country_id' => $country->id],
                [
                    'name' => $country->name,
                    'short_name' => $country->iso2_code ?? $country->iso_code,
                    'logo_url' => $country->flag_url,
                    'is_national_team' => true,
                ]
            );
        }
    }
}
