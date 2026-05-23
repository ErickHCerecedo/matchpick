<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class GameMatch extends Model
{
    protected $table = 'matches';
    protected $fillable = ['round_id', 'home_team_id', 'away_team_id', 'scheduled_at', 'venue', 'status', 'prediction_closes_at', 'external_id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'prediction_closes_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo { return $this->belongsTo(Round::class); }
    public function homeTeam(): BelongsTo { return $this->belongsTo(Team::class, 'home_team_id'); }
    public function awayTeam(): BelongsTo { return $this->belongsTo(Team::class, 'away_team_id'); }
    public function result(): HasOne { return $this->hasOne(MatchResult::class, 'match_id'); }
    public function predictions(): HasMany { return $this->hasMany(Prediction::class, 'match_id'); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('prediction_closes_at', '>', now());
    }

    public function isPredictionOpen(): bool
    {
        return $this->prediction_closes_at?->isFuture() ?? false;
    }
}
