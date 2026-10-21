<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->input('id');

        $rules = [
            'name' => 'alpha|max:255|unique:roles,name,'.$roleId,
            'permissions' => 'array',
            'description' => 'string|min:2|max:255|nullable',
        ];

        if ($this->method() === 'POST') {
            $rules['name'] = 'required|'.$rules['name'];
        }

        return $rules;
    }
}
