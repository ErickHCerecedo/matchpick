<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'prediction_closes_at' => $this->prediction_closes_at?->toIso8601String(),
            'venue' => $this->venue,
            'status' => $this->status,
            'is_prediction_open' => $this->isPredictionOpen(),
            'bracket_slot' => $this->bracket_slot,
            'home_placeholder' => $this->home_placeholder,
            'away_placeholder' => $this->away_placeholder,
            'home_team' => $this->whenLoaded('homeTeam', fn() => [
                'id' => $this->homeTeam->id,
                'name' => $this->homeTeam->name,
                'short_name' => $this->homeTeam->short_name,
                'flag_url' => $this->homeTeam->country?->flag_url,
            ]),
            'away_team' => $this->whenLoaded('awayTeam', fn() => [
                'id' => $this->awayTeam->id,
                'name' => $this->awayTeam->name,
                'short_name' => $this->awayTeam->short_name,
                'flag_url' => $this->awayTeam->country?->flag_url,
            ]),
            'result' => $this->whenLoaded('result', fn() => $this->result ? [
                'home_score' => $this->result->home_score,
                'away_score' => $this->result->away_score,
                'winner' => $this->result->winner,
            ] : null),
        ];
    }
}
