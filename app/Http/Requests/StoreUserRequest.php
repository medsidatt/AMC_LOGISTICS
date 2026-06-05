<?php

namespace App\Http\Requests;

use App\Http\Concerns\AssignableRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use AssignableRoles;

    public function authorize(): bool
    {
        // Route is already guarded by permission:user-create middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::in($this->assignableRoleIds())],
        ];
    }
}
