<x-modal-header-body>
    <x-slot name="title">
        {{ __('Create User') }}
    </x-slot>

    <div id="createUserForm">
        <form action="{{ route('invitation.send') }}" method="post">
            @csrf
            <x-forms.input label="Email" name="email" type="email" required="required"/>
            <x-buttons.save
                container="createUserForm"
                value="{{ __('Send invitation') }}"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>
