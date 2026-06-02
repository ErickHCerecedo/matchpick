<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuinielaDeleteVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['quiniela_id', 'user_id'];

    public function quiniela(): BelongsTo
    {
        return $this->belongsTo(Quiniela::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
