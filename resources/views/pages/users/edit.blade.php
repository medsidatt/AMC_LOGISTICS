<x-modal-header-body>
    <x-slot name="title">
        {{ __('global.edit') }} {{ $user->name }}
    </x-slot>
    <div id="editUserForm">
        <form action="{{ route('users.update', $user->id) }}" method="post">
            @csrf
            @method('PUT')
            <x-forms.input :label="__('Name')" name="name" :value="$user->name"/>
            <x-forms.input :label="__('Email')" name="email" :value="$user->email"/>
            <div class="form-group">
                <label for="role">{{ __('user.role') }}</label>
                <select
                    multiple
                    name="roles[]" id="role" class="form-control select2">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{
                            in_array($role->id, $user->roles->pluck('id')->toArray()) ? 'selected' : ''
                         }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-buttons.save
                container="editUserForm"
                onclick="saveForm({ element: this })"
            />
        </form>
    </div>
</x-modal-header-body>
