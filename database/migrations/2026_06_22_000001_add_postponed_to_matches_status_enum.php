<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUSES = "'scheduled','in_progress','finished','cancelled','postponed','suspended','paused','rescheduled'";

    public function up(): void
    {
        // Laravel's enum() on PostgreSQL creates a check constraint named {table}_{column}_check
        DB::statement('ALTER TABLE matches DROP CONSTRAINT IF EXISTS matches_status_check');
        DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN (' . self::STATUSES . '))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE matches DROP CONSTRAINT IF EXISTS matches_status_check');
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('scheduled','in_progress','finished','cancelled'))");
    }
};
