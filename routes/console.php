<?php

use Illuminate\Support\Facades\Schedule;

// Results are synced manually by quiniela admins (no auto-sync).

// Check every hour whether any round deadline falls within a 24h or 12h window
// and send reminder emails to participants who haven't completed their predictions.
Schedule::command('reminders:send-round-deadlines')->hourly();
