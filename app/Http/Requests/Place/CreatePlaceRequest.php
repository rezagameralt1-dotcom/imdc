<?php

namespace App\Http\Requests\Place;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreatePlaceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::in(['building', 'zone', 'landmark', 'other'])],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric'],
            'owner_did' => ['nullable', 'uuid'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('type')) {
            $this->merge(['type' => 'building']);
        }
    }
}
