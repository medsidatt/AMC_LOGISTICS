<div id="edit-transporter-form">
    <form action="{{ route('transporters.update', $transporter->id) }}" method="post">
        @csrf
        @method('PUT')
        <div class="row">
            <x-forms.input
                class="col-md-6"
                name="name"
                type="text"
                :label="__('Nom')"
                required
                :value="$transporter->name"
            />
            <x-forms.input
                class="col-md-6"
                name="phone"
                :label="__('Téléphone')"
                :value="$transporter->phone"
            />
            <x-forms.input
                class="col-md-6"
                name="email"
                type="email"
                :label="__('Email')"
                :value="$transporter->email"
            />
            <x-forms.input
                class="col-md-6"
                name="address"
                :label="__('Adresse')"
                :value="$transporter->address"
            />
            <x-forms.input
                class="col-md-6"
                name="website"
                :label="__('Site Web')"
                :value="$transporter->website"
            />
        </div>
        <x-buttons.save
            container="edit-transporter-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>


