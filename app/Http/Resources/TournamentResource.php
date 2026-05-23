<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'season' => $this->season,
            'logo_url' => $this->logo_url,
            'is_active' => (bool) $this->is_active,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'rounds_count' => $this->whenLoaded('rounds', fn() => $this->rounds->count()),
        ];
    }
}
