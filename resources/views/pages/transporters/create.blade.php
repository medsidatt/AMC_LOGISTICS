<div id="create-transporter-form">
    <form action="{{ route('transporters.store') }}" method="post">
        @csrf
        <div class="row">
            <x-forms.input
                class="col-md-6"
                name="name"
                type="text"
                :label="__('Nom')"
                required
            />
            <x-forms.input
                class="col-md-6"
                name="phone"
                type="text"
                :label="__('Téléphone')"
            />
            <x-forms.input
                class="col-md-6"
                name="email"
                type="email"
                :label="__('Email')"
            />
            <x-forms.input
                class="col-md-6"
                name="address"
                type="text"
                :label="__('Adresse')"
            />
            <x-forms.input
                class="col-md-6"
                name="website"
                :label="__('Site Web')"
            />
        </div>
        <x-buttons.save
            container="create-transporter-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>


