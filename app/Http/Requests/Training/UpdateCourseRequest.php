<?php

namespace App\Http\Requests\Training;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'level' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'archived'])],
        ];
    }
}
