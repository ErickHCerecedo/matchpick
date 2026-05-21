<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Invitation extends Model
{
    public $timestamps = false;
    protected $fillable = ['quiniela_id', 'invited_by', 'email', 'token', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function quiniela(): BelongsTo { return $this->belongsTo(Quiniela::class); }
    public function invitedBy(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}
