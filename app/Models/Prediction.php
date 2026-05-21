<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prediction extends Model
{
    protected $fillable = ['user_id', 'quiniela_id', 'match_id', 'home_score', 'away_score'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function quiniela(): BelongsTo { return $this->belongsTo(Quiniela::class); }
    public function match(): BelongsTo { return $this->belongsTo(Match::class); }
    public function score(): HasOne { return $this->hasOne(Score::class); }
}
