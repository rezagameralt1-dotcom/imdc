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
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Extract Idempotency-Key header if not in body (case-insensitive)
        if (!$this->has('idempotency_key')) {
            $headerKey = $this->header('Idempotency-Key')
                      ?? $this->header('idempotency-key')
                      ?? $this->header('IDEMPOTENCY-KEY');

            if ($headerKey) {
                $this->merge(['idempotency_key' => trim($headerKey)]);
            }
        }
    }
}
