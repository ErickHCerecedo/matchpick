<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    protected $fillable = ['name', 'short_name', 'country_id', 'tournament_id', 'logo_url', 'is_national_team', 'external_id'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
