<?php

namespace App\Http\Requests\Linking;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreateDidOrderLinkRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'did_id' => ['required', 'uuid'],
            'order_id' => ['required', 'uuid'],
            'scope' => ['nullable', 'string', Rule::in(['ownership', 'billing', 'beneficiary'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('scope')) {
            $this->merge(['scope' => 'ownership']);
        }
    }
}
