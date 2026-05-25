<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\Round;
use App\Notifications\RoundDeadlineReminder;
use Illuminate\Console\Command;

/**
 * Sends prediction deadline reminders to quiniela participants.
 *
 * Runs every hour. For each round whose earliest open prediction_closes_at
 * falls within ±30 min of now+24h or now+12h, checks which participants
 * are missing predictions and notifies them.
 *
 * The ±30 min window guarantees each notification fires exactly once per
 * type (24h / 12h) per round when the command runs on the hour.
 */
class SendRoundReminders extends Command
{
    protected $signature   = 'reminders:send-round-deadlines';
    protected $description = 'Send prediction deadline reminders 24h and 12h before each round closes';

    private const WINDOWS = [
        ['hours' => 24, 'margin' => 30],
        ['hours' => 12, 'margin' => 30],
    ];

    public function handle(): int
    {
        $sent = 0;

        foreach (self::WINDOWS as ['hours' => $hours, 'margin' => $margin]) {
            $from = now()->addHours($hours)->subMinutes($margin);
            $to   = now()->addHours($hours)->addMinutes($margin);

            // Rounds that have at least one match closing in this window
            $roundIds = GameMatch::whereBetween('prediction_closes_at', [$from, $to])
                ->where('status', 'scheduled')
                ->pluck('round_id')
                ->unique();

            foreach ($roundIds as $roundId) {
                $sent += $this->processRound($roundId, $hours);
            }
        }

        $this->info("Sent {$sent} reminder(s).");
        return self::SUCCESS;
    }

    private function processRound(int $roundId, int $hoursLeft): int
    {
        $round = Round::with('tournament')->find($roundId);
        if (!$round) {
            return 0;
        }

        // Open matches in this round (predictions not yet closed)
        $openMatches = GameMatch::where('round_id', $roundId)
            ->where('prediction_closes_at', '>', now())
            ->get();

        if ($openMatches->isEmpty()) {
            return 0;
        }

        $deadline    = $openMatches->min('prediction_closes_at');
        $openMatchIds = $openMatches->pluck('id');

        // Active quinielas for this tournament
        $quinielas = Quiniela::where('tournament_id', $round->tournament_id)
            ->where('is_active', true)
            ->where('predictions_open', true)
            ->with('participants')
            ->get();

        $sent = 0;

        foreach ($quinielas as $quiniela) {
            foreach ($quiniela->participants as $user) {
                $predictedCount = Prediction::where('user_id', $user->id)
                    ->where('quiniela_id', $quiniela->id)
                    ->whereIn('match_id', $openMatchIds)
                    ->count();

                $missing = $openMatchIds->count() - $predictedCount;

                if ($missing <= 0) {
                    continue;
                }

                $user->notify(new RoundDeadlineReminder(
                    quiniela: $quiniela,
                    round:    $round,
                    deadline: \Carbon\Carbon::parse($deadline),
                    hoursLeft: $hoursLeft,
                    missing:  $missing,
                    total:    $openMatchIds->count(),
                ));

                $sent++;
            }
        }

        return $sent;
    }
}
