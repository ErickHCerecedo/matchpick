<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wildcard extends Model
{
    protected $fillable = ['user_id', 'quiniela_id', 'team1_id', 'team2_id', 'team3_id', 'points_earned'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiniela(): BelongsTo
    {
        return $this->belongsTo(Quiniela::class);
    }

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function team3(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team3_id');
    }
}
