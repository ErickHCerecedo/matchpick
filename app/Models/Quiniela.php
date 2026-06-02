<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Quiniela extends Model
{
    use SoftDeletes;

    protected $fillable = ['creator_id', 'tournament_id', 'name', 'slug', 'description', 'type', 'scoring_type', 'max_participants', 'is_active', 'predictions_open'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'predictions_open' => 'boolean'];
    }

    public static function generateSlug(string $name): string
    {
        return Str::slug($name) . '-' . Str::lower(Str::random(6));
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiniela_user')
            ->withPivot('role', 'joined_at')
            ->orderBy('quiniela_user.joined_at');
    }

    public function invitations(): HasMany { return $this->hasMany(Invitation::class); }
    public function predictions(): HasMany { return $this->hasMany(Prediction::class); }
    public function standings(): HasMany { return $this->hasMany(Standing::class)->orderByDesc('total_points'); }
    public function deleteVotes(): HasMany { return $this->hasMany(QuinielaDeleteVote::class); }

    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
}
