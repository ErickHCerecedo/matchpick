<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\GameMatch;

class Tournament extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'season', 'logo_url', 'starts_at', 'ends_at', 'is_active', 'meta'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('order');
    }

    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(GameMatch::class, Round::class);
    }

    public function quinielas(): HasMany
    {
        return $this->hasMany(Quiniela::class);
    }
}
