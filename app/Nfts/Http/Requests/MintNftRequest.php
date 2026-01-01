<?php

namespace App\Nfts\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class MintNftRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'contract' => ['required', 'string', 'max:255'],
            'token_id' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['required', 'uuid'],
            'metadata_uri' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
