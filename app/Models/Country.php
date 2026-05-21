<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['name', 'iso_code', 'iso2_code', 'flag_url'];

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
