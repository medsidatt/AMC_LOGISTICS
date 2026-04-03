<x-modal-header-body
    :title="__('global.entity_edit')"
>
    <div id="edit-entity-form">
        <form action="{{ route('entities.update', $entity->slug) }}" method="post">
            @csrf
            @method('PUT')
            <div class="row">
                <x-forms.input
                    class="col-md-6"
                    name="name"
                    label="Name"
                    required="true"
                    value="{{ $entity->name }}"
                /> {{-- Name --}}
                <x-forms.input
                    class="col-md-6"
                    name="principal_activity"
                    label="Activite Principale"
                    required="true"
                    value="{{ $entity->principal_activity }}"
                /> {{-- Principal activity --}}
                <x-forms.input
                    class="col-md-6"
                    name="matricule_cnss"
                    label="CNSS Matricule"
                    required="true"
                    value="{{ $entity->matricule_cnss }}"
                /> {{-- Matricule CNSS --}}
                <x-forms.input
                    class="col-md-6"
                    name="matricule_cnam"
                    label="CNAM Matricule"
                    required="true"
                    value="{{ $entity->matricule_cnam }}"
                /> {{-- Matricule CNAM --}}
                <x-forms.input
                    class="col-md-6"
                    name="nif"
                    label="NIF"
                    required="true"
                    value="{{ $entity->nif }}"
                /> {{-- NIF --}}
                <x-forms.input
                    class="col-md-6"
                    name="rc"
                    label="RC"
                    required="true"
                    value="{{ $entity->rc }}"
                /> {{-- RC --}}
                <x-forms.input
                    class="col-md-6"
                    name="address"
                    label="Address"
                    required="true"
                    value="{{ $entity->address }}"
                /> {{-- Address --}}
                <x-forms.input
                    class="col-md-2"
                    name="ilot"
                    label="Ilot"
                    required="true"
                    value="{{ $entity->ilot }}"
                /> {{-- Ilot --}}
                <x-forms.input
                    class="col-md-2"
                    name="lot"
                    label="Lot"
                    required="true"
                    value="{{ $entity->lot }}"
                /> {{-- Lot --}}
                <x-forms.input
                    class="col-md-2"
                    name="city"
                    label="Ville"
                    required="true"
                    value="{{ $entity->city }}"
                /> {{-- City --}}
                <x-forms.input
                    class="col-md-6"
                    name="phone"
                    label="Phone"
                    required="true"
                    value="{{ $entity->phone }}"
                /> {{-- Phone --}}
                <x-forms.input
                    class="col-md-6"
                    name="email"
                    label="Email"
                    type="email"
                    required="true"
                    value="{{ $entity->email }}"
                /> {{-- Email --}}
                <x-forms.input
                    class="col-md-6"
                    name="bp"
                    label="Poite parole"
                    value="{{ $entity->bp }}"
                /> {{-- BP --}}
                <x-forms.input
                    class="col-md-6"
                    name="website"
                    label="Website"
                    type="url"
                    value="{{ $entity->website }}"
                /> {{-- Website --}}
                <x-forms.textarea
                    class="col-md-6"
                    name="description"
                    label="Description"
                    value="{{ $entity->description }}"
                    colls="4"
                /> {{-- Description --}}
                @if($entity->logo)
                    <div class="col-md-6">
                        <label for="logo">Logo</label>
                        <div class="mb-1">
                            <img src="{{ asset('storage/' . $entity->logo) }}"
                                 alt="Logo"
                                 class="img-thumbnail"
                                 style="max-width: 100px; max-height: 100px;">
                        </div>
                        <x-forms.input
                            name="logo"
                            type="file"
                        />
                    </div> {{-- Logo --}}
                @else
                    <x-forms.input
                        class="col-md-6"
                        name="logo"
                        label="Logo"
                        type="file"
                    /> {{-- Logo --}}
                @endif
            </div>
            <x-buttons.save
                container="edit-entity-form"
                onclick="saveForm({ element: this})"
            />
        </form>
    </div>
</x-modal-header-body>

