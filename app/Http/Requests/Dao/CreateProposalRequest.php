<?php

namespace App\Http\Requests\Dao;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreateProposalRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'created_by_did' => ['required', 'uuid'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'closed', 'executed'])],
            'voting_starts_at' => ['nullable', 'date'],
            'voting_ends_at' => ['nullable', 'date', 'after:voting_starts_at'],
            'quorum' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
