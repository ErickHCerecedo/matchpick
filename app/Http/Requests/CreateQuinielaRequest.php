<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateQuinielaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'tournament_id' => 'required|exists:tournaments,id',
            'type' => 'sometimes|in:public,private',
            'description' => 'nullable|string|max:500',
            'max_participants' => 'nullable|integer|min:2',
        ];
    }
}
