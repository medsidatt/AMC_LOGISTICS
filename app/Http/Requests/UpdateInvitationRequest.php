<?php

namespace App\Http\Requests;

use App\Http\Concerns\AssignableRoles;
use App\Models\Auth\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvitationRequest extends FormRequest
{
    use AssignableRoles;

    public function authorize(): bool
    {
        // Route is already guarded by permission:invitation-edit middleware.
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $invitationEmail = optional(\App\Models\Auth\Invitation::find($id))->email;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore(
                    optional(User::where('email', $invitationEmail)->first())->id
                )->whereNull('deleted_at'),
                Rule::unique('invitations', 'email')->ignore($id)->whereNull('deleted_at'),
            ],
            'role_name' => ['required', 'string', Rule::in($this->assignableRoleNames())],
        ];
    }
}
