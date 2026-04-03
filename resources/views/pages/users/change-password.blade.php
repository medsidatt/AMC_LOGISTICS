<x-modal-header-body
    title="Change Password">
    <div id="change-password-form">
        <form method="post" action="{{ route('users.change-user-password', $user->id) }}">
            @csrf
            @method('put')
            <div class="row">
                <x-forms.input
                    type="password"
                    class="col-md-12"
                    name="password"
                    label="New Password"
                />
                <x-forms.input
                    type="password"
                    class="col-md-12"
                    name="password_confirmation"
                    label="Confirm Password"
                />
            </div>
            <x-buttons.save
                container="change-password-form"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>
