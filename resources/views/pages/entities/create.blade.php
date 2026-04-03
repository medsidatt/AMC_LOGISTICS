<x-modal-header-body
    :title="__('global.entity_create')"
>
    <div id="create-entity-form">
        <form action="{{ route('entities.store') }}" method="post">
            @csrf
            <div class="row">
                <x-forms.input
                    class="col-md-6"
                    name="name"
                    label="Name"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="matricule_cnss"
                    label="CNSS Matricule"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="matricule_cnam"
                    label="CNAM Matricule"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="nif"
                    label="NIF"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="rc"
                    label="RC"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="address"
                    label="Address"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="phone"
                    label="Phone"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="email"
                    label="Email"
                    type="email"
                    required="true"
                />
                <x-forms.input
                    class="col-md-6"
                    name="website"
                    label="Website"
                    type="url"
                />
                <x-forms.input
                    class="col-md-6"
                    name="logo"
                    label="Logo"
                    type="file"
                />

                <x-forms.textarea
                    class="col-md-12"
                    name="description"
                    label="Description"
                />

            </div>

            <x-buttons.save
                container="create-entity-form"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>

