<?php

use Database\Seeders\CountrySeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\RoundSeeder;
use Database\Seeders\MatchSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time data migration for WC 2026 initial data.
 *
 * Runs automatically on `php artisan migrate` and is tracked in the
 * migrations table — Laravel will never run it again after the first time.
 *
 * Safe to deploy on any environment: seeders use guards / firstOrCreate /
 * insertOrIgnore so duplicate data is never inserted.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CountrySeeder())->run();
        (new TeamSeeder())->run();
        (new RoundSeeder())->run();
        (new MatchSeeder())->run();
    }

    public function down(): void
    {
        // Intentionally empty — do not reverse reference data in production.
    }
};
