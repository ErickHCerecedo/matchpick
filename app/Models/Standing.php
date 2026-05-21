<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    public $timestamps = false;
    protected $fillable = ['quiniela_id', 'user_id', 'total_points', 'exact_scores', 'correct_results', 'predictions_made', 'rank'];

    public function quiniela(): BelongsTo { return $this->belongsTo(Quiniela::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
