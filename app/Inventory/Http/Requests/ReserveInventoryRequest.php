<?php

namespace App\Inventory\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class ReserveInventoryRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid'],
            'product_id' => ['required', 'uuid'],
            'qty' => ['required', 'integer', 'min:1'],
            'shop_customer_id' => ['required', 'string'],
        ];
    }
}

