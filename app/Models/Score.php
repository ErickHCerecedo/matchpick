<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    public $timestamps = false;
    protected $fillable = ['prediction_id', 'match_result_id', 'points', 'breakdown'];

    protected function casts(): array
    {
        return ['breakdown' => 'array', 'calculated_at' => 'datetime'];
    }

    public function prediction(): BelongsTo { return $this->belongsTo(Prediction::class); }
    public function matchResult(): BelongsTo { return $this->belongsTo(MatchResult::class); }
}
