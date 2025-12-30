<?php

namespace App\Products\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class StoreProductRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}



