<?php

namespace App\Nfts\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class TransferNftRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'token_uuid' => ['required', 'uuid'],
            'to_user_id' => ['required', 'uuid'],
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
