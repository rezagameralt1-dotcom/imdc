<?php

namespace App\Nfts\Http\Requests;

use App\Http\Requests\BaseApiRequest;
use App\Rules\ExistsInCoreUsers;

class MintNftRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'contract' => ['required', 'string', 'max:255'],
            'token_id' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['required', 'integer', 'min:1', new ExistsInCoreUsers()],
            'metadata_uri' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
