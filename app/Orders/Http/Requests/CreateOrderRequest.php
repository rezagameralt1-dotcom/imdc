<?php

namespace App\Orders\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class CreateOrderRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'meta' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}



