<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE matches MODIFY COLUMN status ENUM('scheduled','in_progress','finished','cancelled','postponed','suspended','paused','rescheduled') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE matches MODIFY COLUMN status ENUM('scheduled','in_progress','finished','cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
