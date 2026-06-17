<?php

use Illuminate\Support\Facades\Schedule;

// Auto-sync is currently disabled — matches are managed manually from the admin panel.
// Schedule::command('matches:wc-auto-sync')->everyMinute();

// Check every hour whether any round deadline falls within a 24h or 12h window
// and send reminder emails to participants who haven't completed their predictions.
Schedule::command('reminders:send-round-deadlines')->hourly();
