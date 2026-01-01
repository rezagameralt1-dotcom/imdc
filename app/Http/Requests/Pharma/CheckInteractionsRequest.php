<?php

namespace App\Http\Requests\Pharma;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CheckInteractionsRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'drug_ids' => ['required', 'array', 'min:2'],
            'drug_ids.*' => ['required', 'uuid'],
        ];
    }
}
