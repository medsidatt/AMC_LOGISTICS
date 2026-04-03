
<div id="edit-driver-form">
    <form action="{{ route('drivers.update', $driver->id) }}" method="post">
        @csrf
        @method('PUT')
        <div class="row">
            <x-forms.input
                class="col-md-6"
                name="name"
                type="text"
                :label="__('Nom')"
                required
                :value="$driver->name"
            />
            <x-forms.input
                class="col-md-6"
                name="phone"
                type="text"
                :label="__('Téléphone')"
                :value="$driver->phone"
            />
            <x-forms.input
                class="col-md-6"
                name="email"
                type="email"
                :label="__('Email')"
                :value="$driver->email"
            />
            <x-forms.input
                class="col-md-6"
                name="address"
                type="text"
                :label="__('Adresse')"
                :value="$driver->address"
            />

        </div>

        <x-buttons.save
            container="edit-driver-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>




