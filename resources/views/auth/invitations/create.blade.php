<x-modal-header-body>
    <x-slot name="title">
        {{ __('Create User') }}
    </x-slot>

    <div id="createUserForm">
        <form action="{{ route('invitation.send') }}" method="post">
            @csrf
            <x-forms.input label="Email" name="email" type="email" required="required"/>
            <div class="form-group">
                <label for="role_name">Role</label>
                <select name="role_name" id="role_name" class="form-control" required>
                    <option value="">{{ __('Select a role') }}</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-buttons.save
                container="createUserForm"
                value="{{ __('Send invitation') }}"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>
