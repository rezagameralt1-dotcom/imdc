<?php

namespace App\Inventory\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class AdjustInventoryRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}


