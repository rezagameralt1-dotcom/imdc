<?php

namespace App\Http\Requests\Dao;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CastVoteRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'voter_did' => ['required', 'uuid'],
            'vote' => ['required', 'string', Rule::in(['yes', 'no', 'abstain'])],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
