<?php

namespace App\Http\Requests\Training;

use App\Http\Requests\BaseApiRequest;

class CompleteEnrollmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
