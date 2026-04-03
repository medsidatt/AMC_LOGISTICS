<x-modal-header-body
    title="{{ __('user.create_user') }}"
>

    <div id="createUserForm">
        <form action="{{ route('users.store') }}" method="post">
            @csrf
            <div class="row">
                <x-forms.input
                    class="col-md-12"
                    label="Name" name="name" required="required"/>
                <x-forms.input
                    class="col-md-12"
                    label="Email" name="email" type="email" required="required"/>
                <div class="col-md-12 form-group">
                    <label for="roles">{{ __('user.role') }}</label>
                    <select multiple name="roles[]" id="roles" class="form-control select2">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <x-buttons.save
                container="createUserForm"
                value="{{ __('Create User') }}"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>
