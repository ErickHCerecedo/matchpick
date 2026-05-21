<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StandingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'rank' => $this->rank,
            'total_points' => $this->total_points,
            'exact_scores' => $this->exact_scores,
            'correct_results' => $this->correct_results,
            'predictions_made' => $this->predictions_made,
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ]),
        ];
    }
}
