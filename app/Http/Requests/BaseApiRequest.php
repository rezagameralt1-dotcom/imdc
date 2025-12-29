<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'message' => 'Validation failed',
                'details' => $validator->errors(),
            ],
            'trace_id' => $this->request->attributes->get('trace_id'),
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'message' => 'Forbidden',
            ],
            'trace_id' => $this->request->attributes->get('trace_id'),
        ], 403));
    }
}

