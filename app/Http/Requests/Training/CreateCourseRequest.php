<?php

namespace App\Http\Requests\Training;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class CreateCourseRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'level' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'archived'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('status')) {
            $this->merge(['status' => 'draft']);
        }
    }
}
