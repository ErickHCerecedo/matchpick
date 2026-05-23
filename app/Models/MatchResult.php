<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\GameMatch;

class MatchResult extends Model
{
    protected $fillable = ['match_id', 'home_score', 'away_score', 'home_score_penalties', 'away_score_penalties', 'winner', 'confirmed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::saving(function (self $result) {
            if ($result->home_score > $result->away_score) $result->winner = 'home';
            elseif ($result->away_score > $result->home_score) $result->winner = 'away';
            else $result->winner = 'draw';
        });
    }

    public function match(): BelongsTo { return $this->belongsTo(GameMatch::class); }
    public function scores(): HasMany { return $this->hasMany(Score::class); }
}
