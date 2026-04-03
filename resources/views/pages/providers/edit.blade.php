<div id="edit-provider-form">
    <form action="{{ route('providers.update', $provider->id) }}" method="post">
        @csrf
        @method('PUT')
        <div class="row">
            <x-forms.input
                class="col-md-6"
                name="name"
                type="text"
                :label="__('Nom')"
                required
                :value="$provider->name"
            />
            <x-forms.input
                class="col-md-6"
                name="phone"
                type="text"
                :label="__('Téléphone')"
                :value="$provider->phone"
            />
            <x-forms.input
                class="col-md-6"
                name="email"
                type="email"
                :label="__('Email')"
                :value="$provider->email"
            />
            <x-forms.input
                class="col-md-6"
                name="address"
                type="text"
                :label="__('Adresse')"
                :value="$provider->address"
            />
            <x-forms.input
                class="col-md-6"
                name="website"
                :label="__('Site Web')"
                :value="$provider->website"
            />
        </div>
        <x-buttons.save
            container="edit-provider-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>



