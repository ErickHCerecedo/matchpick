<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'email', 'password', 'avatar_url', 'google_id', 'is_admin'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function quinielas(): HasMany
    {
        return $this->hasMany(Quiniela::class, 'creator_id');
    }

    public function participatingQuinielas(): BelongsToMany
    {
        return $this->belongsToMany(Quiniela::class, 'quiniela_user')
            ->withPivot('role', 'joined_at');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }
}
