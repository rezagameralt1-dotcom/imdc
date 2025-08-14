<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'is_published' => 'sometimes|boolean',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'integer',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer',
        ];
    }
}

