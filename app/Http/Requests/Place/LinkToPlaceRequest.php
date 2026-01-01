<?php

namespace App\Http\Requests\Place;

use App\Http\Requests\BaseApiRequest;

class LinkToPlaceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'nft_id' => ['nullable', 'uuid', 'required_without:did_id'],
            'did_id' => ['nullable', 'uuid', 'required_without:nft_id'],
        ];
    }
}
