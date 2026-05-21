<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuinielaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'type' => 'sometimes|in:public,private',
            'description' => 'nullable|string|max:500',
            'max_participants' => 'nullable|integer|min:2',
            'predictions_open' => 'sometimes|boolean',
        ];
    }
}
