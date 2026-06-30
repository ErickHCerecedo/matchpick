<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkPredictionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'predictions'                      => 'required|array|min:1',
            'predictions.*.match_id'           => 'required|exists:matches,id',
            'predictions.*.home_score'         => 'required|integer|min:0|max:99',
            'predictions.*.away_score'         => 'required|integer|min:0|max:99',
            'predictions.*.penalties_winner'   => 'nullable|in:home,away',
            'predictions.*.penalties_home'     => 'nullable|integer|min:0|max:30',
            'predictions.*.penalties_away'     => 'nullable|integer|min:0|max:30',
        ];
    }
}
