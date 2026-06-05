<?php

namespace App\Http\Requests;

use App\Http\Concerns\AssignableRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use AssignableRoles;

    public function authorize(): bool
    {
        // Route is guarded by permission:user-edit; the self/Super-Admin guard
        // lives in the controller so it can redirect back with a flash message.
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($id)->whereNull('deleted_at')],
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', Rule::in($this->assignableRoleIds())],
        ];
    }
}
