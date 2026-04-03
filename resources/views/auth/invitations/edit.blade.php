<x-modal-header-body>
    <x-slot name="title">
        {{ __('Edit invitation') }}
    </x-slot>

    <div id="editInvitationForm">
        <form action="{{ route('invitation.update', ['id' => $invitation->id]) }}" method="post">
            @csrf
            @method('PUT')
            <x-forms.input
                value="{{ $invitation->email }}"
                label="Email" name="email" type="email" required="required"/>
            <div class="form-group">
                <label for="role_name">{{ __('Role') }}</label>
                <select name="role_name" id="role_name" class="form-control" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ $invitation->role_name === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-buttons.save
                container="editInvitationForm"
                value="{{ __('Send invitation') }}"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>
