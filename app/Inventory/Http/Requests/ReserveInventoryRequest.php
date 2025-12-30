<?php

namespace App\Inventory\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class ReserveInventoryRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid'],
        ];
    }
}

