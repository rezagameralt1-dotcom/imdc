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
        $traceId = $this->attributes->get('trace_id')
            ?? $this->header('X-Trace-Id');

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'message' => 'Validation failed',
                    'fields' => $validator->errors()->toArray(),
                ],
                'trace_id' => $traceId,
            ], 422)
        );
    }
}
