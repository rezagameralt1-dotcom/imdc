<?php

namespace App\Orders\Http\Requests;

use App\Http\Requests\BaseApiRequest;

class CreateOrderRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'meta' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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



