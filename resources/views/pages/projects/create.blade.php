<section id="create-project-form">
    <form action="{{ route('projects.store') }}" method="POST">
        @csrf
        <div class="row">
            <x-forms.input
                name="name"
                label="Name"
                class="col-6 col-md-4"
                required
            />
            <x-forms.input
                name="code"
                label="Code"
                class="col-6 col-md-4"
                required
            />
            <x-forms.select
                name="entity_id"
                label="Entite"
                class="col-6 col-md-4"
                :options="$entities"
                required
            />
            <x-forms.input
                type="date"
                name="start_date"
                label="Start Date"
                class="col-6 col-md-4"
            />
            <x-forms.input
                type="date"
                name="end_date"
                label="End Date"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="address"
                label="Address"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="phone"
                label="Phone"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="email"
                label="Email"
                type="email"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="matricule_cnss"
                label="CNSS Matricule"
                class="col-6 col-md-4"
            />

            <x-forms.input
                name="matricule_cnam"
                label="CNAM Matricule"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="bp"
                label="BP"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="nif"
                label="NIF"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="rc"
                label="RC"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="website"
                label="Website"
                type="url"
                class="col-6 col-md-4"
            />
            <x-forms.input
                name="logo"
                label="Logo"
                type="file"
                class="col-6 col-md-4"
            />
            <x-forms.textarea
                name="description"
                label="Description"
                class="col-12"
                rows="4"
            />

        </div>
        <x-buttons.save
            container="create-project-form"
            onclick="saveForm({element: this, modal: 'dynamic'})"
        />
    </form>
</section>
