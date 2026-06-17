<?php

use Illuminate\Support\Facades\Schedule;

// Auto-sync WC match statuses and scores from football-data.org.
// Starts matches automatically, updates live score every minute,
// and finalises the result + triggers scoring jobs when finished.
// withoutOverlapping() prevents double-runs if a tick takes > 60s.
Schedule::command('matches:wc-auto-sync')->everyMinute()->withoutOverlapping();

// Check every hour whether any round deadline falls within a 24h or 12h window
// and send reminder emails to participants who haven't completed their predictions.
Schedule::command('reminders:send-round-deadlines')->hourly();
