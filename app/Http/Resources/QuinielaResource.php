<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuinielaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'predictions_open' => $this->predictions_open,
            'wildcard_enabled'   => (bool) $this->wildcard_enabled,
            'penalties_enabled'  => (bool) $this->penalties_enabled,
            'max_participants' => $this->max_participants,
            'participants_count' => $this->participants_count ?? $this->whenLoaded('participants', fn() => $this->participants->count(), 0),
            'my_role' => $this->when(
                $this->relationLoaded('participants') && $request->user(),
                fn() => $this->participants->where('id', $request->user()?->id)->first()?->pivot->role
            ),
            'tournament' => $this->whenLoaded('tournament', fn() => new TournamentResource($this->tournament)),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'avatar_url' => $this->creator->avatar_url,
            ]),
        ];
    }
}
