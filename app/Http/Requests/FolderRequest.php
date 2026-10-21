<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'string|max:255',
            'description' => 'string|min:2|max:255|nullable',
        ];

        if ($this->method() === 'POST') {
            $rules['name'] = 'required|'.$rules['name'];
        }

        return $rules;
    }
}
