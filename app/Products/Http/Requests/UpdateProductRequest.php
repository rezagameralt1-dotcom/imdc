<?php

namespace App\Products\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class UpdateProductRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}



