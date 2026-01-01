<?php

namespace App\Http\Requests\Linking;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreateOrderNftLinkRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid'],
            'nft_id' => ['required', 'uuid'],
            'purpose' => ['nullable', 'string', Rule::in(['fulfillment', 'collateral', 'reward'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('purpose')) {
            $this->merge(['purpose' => 'fulfillment']);
        }
    }
}
