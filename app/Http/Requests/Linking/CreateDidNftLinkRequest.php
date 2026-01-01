<?php

namespace App\Http\Requests\Linking;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreateDidNftLinkRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'did_id' => ['required', 'uuid'],
            'nft_id' => ['required', 'uuid'],
            'role' => ['nullable', 'string', Rule::in(['owner', 'issuer', 'beneficiary'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('role')) {
            $this->merge(['role' => 'owner']);
        }
    }
}
