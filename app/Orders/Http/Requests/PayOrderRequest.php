<?php

namespace App\Orders\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class PayOrderRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}



