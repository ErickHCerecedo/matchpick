<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            // CONMEBOL (6)
            ['name' => 'Argentina', 'iso_code' => 'ARG', 'iso2_code' => 'ar'],
            ['name' => 'Brazil', 'iso_code' => 'BRA', 'iso2_code' => 'br'],
            ['name' => 'Colombia', 'iso_code' => 'COL', 'iso2_code' => 'co'],
            ['name' => 'Ecuador', 'iso_code' => 'ECU', 'iso2_code' => 'ec'],
            ['name' => 'Uruguay', 'iso_code' => 'URU', 'iso2_code' => 'uy'],
            ['name' => 'Venezuela', 'iso_code' => 'VEN', 'iso2_code' => 've'],
            // UEFA (16)
            ['name' => 'Germany', 'iso_code' => 'GER', 'iso2_code' => 'de'],
            ['name' => 'France', 'iso_code' => 'FRA', 'iso2_code' => 'fr'],
            ['name' => 'Spain', 'iso_code' => 'ESP', 'iso2_code' => 'es'],
            ['name' => 'England', 'iso_code' => 'ENG', 'iso2_code' => 'gb-eng'],
            ['name' => 'Portugal', 'iso_code' => 'POR', 'iso2_code' => 'pt'],
            ['name' => 'Netherlands', 'iso_code' => 'NED', 'iso2_code' => 'nl'],
            ['name' => 'Belgium', 'iso_code' => 'BEL', 'iso2_code' => 'be'],
            ['name' => 'Italy', 'iso_code' => 'ITA', 'iso2_code' => 'it'],
            ['name' => 'Croatia', 'iso_code' => 'CRO', 'iso2_code' => 'hr'],
            ['name' => 'Switzerland', 'iso_code' => 'SUI', 'iso2_code' => 'ch'],
            ['name' => 'Austria', 'iso_code' => 'AUT', 'iso2_code' => 'at'],
            ['name' => 'Scotland', 'iso_code' => 'SCO', 'iso2_code' => 'gb-sct'],
            ['name' => 'Hungary', 'iso_code' => 'HUN', 'iso2_code' => 'hu'],
            ['name' => 'Turkey', 'iso_code' => 'TUR', 'iso2_code' => 'tr'],
            ['name' => 'Serbia', 'iso_code' => 'SRB', 'iso2_code' => 'rs'],
            ['name' => 'Denmark', 'iso_code' => 'DEN', 'iso2_code' => 'dk'],
            // CONCACAF (6)
            ['name' => 'United States', 'iso_code' => 'USA', 'iso2_code' => 'us'],
            ['name' => 'Canada', 'iso_code' => 'CAN', 'iso2_code' => 'ca'],
            ['name' => 'Mexico', 'iso_code' => 'MEX', 'iso2_code' => 'mx'],
            ['name' => 'Panama', 'iso_code' => 'PAN', 'iso2_code' => 'pa'],
            ['name' => 'Honduras', 'iso_code' => 'HON', 'iso2_code' => 'hn'],
            ['name' => 'Costa Rica', 'iso_code' => 'CRC', 'iso2_code' => 'cr'],
            // AFC (8)
            ['name' => 'Japan', 'iso_code' => 'JPN', 'iso2_code' => 'jp'],
            ['name' => 'South Korea', 'iso_code' => 'KOR', 'iso2_code' => 'kr'],
            ['name' => 'Saudi Arabia', 'iso_code' => 'KSA', 'iso2_code' => 'sa'],
            ['name' => 'Iran', 'iso_code' => 'IRN', 'iso2_code' => 'ir'],
            ['name' => 'Australia', 'iso_code' => 'AUS', 'iso2_code' => 'au'],
            ['name' => 'Qatar', 'iso_code' => 'QAT', 'iso2_code' => 'qa'],
            ['name' => 'Uzbekistan', 'iso_code' => 'UZB', 'iso2_code' => 'uz'],
            ['name' => 'Jordan', 'iso_code' => 'JOR', 'iso2_code' => 'jo'],
            // CAF (9)
            ['name' => 'Morocco', 'iso_code' => 'MAR', 'iso2_code' => 'ma'],
            ['name' => 'Senegal', 'iso_code' => 'SEN', 'iso2_code' => 'sn'],
            ['name' => 'Nigeria', 'iso_code' => 'NGA', 'iso2_code' => 'ng'],
            ['name' => 'Cameroon', 'iso_code' => 'CMR', 'iso2_code' => 'cm'],
            ['name' => 'Ghana', 'iso_code' => 'GHA', 'iso2_code' => 'gh'],
            ['name' => 'Ivory Coast', 'iso_code' => 'CIV', 'iso2_code' => 'ci'],
            ['name' => 'Tunisia', 'iso_code' => 'TUN', 'iso2_code' => 'tn'],
            ['name' => 'Algeria', 'iso_code' => 'ALG', 'iso2_code' => 'dz'],
            ['name' => 'Egypt', 'iso_code' => 'EGY', 'iso2_code' => 'eg'],
            // OFC (1)
            ['name' => 'New Zealand', 'iso_code' => 'NZL', 'iso2_code' => 'nz'],
            // Additional qualified teams (added for WC 2026)
            ['name' => 'Ukraine', 'iso_code' => 'UKR', 'iso2_code' => 'ua'],
            ['name' => 'Paraguay', 'iso_code' => 'PAR', 'iso2_code' => 'py'],
            ['name' => 'South Africa', 'iso_code' => 'RSA', 'iso2_code' => 'za'],
            ['name' => 'Czech Republic', 'iso_code' => 'CZE', 'iso2_code' => 'cz'],
            ['name' => 'Bosnia and Herzegovina', 'iso_code' => 'BIH', 'iso2_code' => 'ba'],
            ['name' => 'Haiti', 'iso_code' => 'HAI', 'iso2_code' => 'ht'],
            ['name' => 'Curacao', 'iso_code' => 'CUW', 'iso2_code' => 'cw'],
            ['name' => 'Sweden', 'iso_code' => 'SWE', 'iso2_code' => 'se'],
            ['name' => 'Cape Verde', 'iso_code' => 'CPV', 'iso2_code' => 'cv'],
            ['name' => 'Norway', 'iso_code' => 'NOR', 'iso2_code' => 'no'],
            ['name' => 'Iraq', 'iso_code' => 'IRQ', 'iso2_code' => 'iq'],
            ['name' => 'DR Congo', 'iso_code' => 'COD', 'iso2_code' => 'cd'],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->insertOrIgnore([
                'name' => $country['name'],
                'iso_code' => $country['iso_code'],
                'iso2_code' => $country['iso2_code'],
                'flag_url' => 'https://flagcdn.com/w80/' . $country['iso2_code'] . '.png',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
