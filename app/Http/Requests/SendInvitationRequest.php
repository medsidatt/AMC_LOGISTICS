<?php

namespace App\Http\Requests;

use App\Http\Concerns\AssignableRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends FormRequest
{
    use AssignableRoles;

    public function authorize(): bool
    {
        // Route is already guarded by permission:invitation-create middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                Rule::unique('invitations', 'email')->whereNull('deleted_at'),
            ],
            'role_name' => ['required', 'string', Rule::in($this->assignableRoleNames())],
        ];
    }
}
