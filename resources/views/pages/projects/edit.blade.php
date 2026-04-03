<section id="edit-project-form">
    <form action="{{ route('projects.update', $project->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
            <x-forms.input
                name="name"
                label="Name"
                class="col-6 col-md-4"
                required
                value="{{ $project->name }}"
            />
            <x-forms.input
                readonly
                name="code"
                label="Code"
                class="col-6 col-md-4"
                required
                value="{{ $project->code }}"
            />
            <x-forms.select
                name="entity_id"
                label="Entite"
                class="col-6 col-md-4"
                :options="$entities"
                required
                :value="$project->entity_id"
            />
            <x-forms.input
                type="date"
                name="start_date"
                label="Start Date"
                class="col-6 col-md-4"
                value="{{ $project->start_date }}"
            />
            <x-forms.input
                type="date"
                name="end_date"
                label="End Date"
                class="col-6 col-md-4"
                value="{{ $project->end_date }}"
            />
            <x-forms.input
                name="matricule_cnss"
                label="CNSS Matricule"
                class="col-6 col-md-4"
                value="{{ $project->matricule_cnss }}"
            />
            <x-forms.input
                name="matricule_cnam"
                label="CNAM Matricule"
                class="col-6 col-md-4"
                value="{{ $project->matricule_cnam }}"
            />
            <x-forms.input
                name="bp"
                label="BP"
                class="col-6 col-md-4"
                value="{{ $project->bp }}"
            />
            <x-forms.input
                name="address"
                label="Address"
                class="col-6 col-md-4"
                value="{{ $project->address }}"
            />
            <x-forms.input
                name="phone"
                label="Phone"
                class="col-6 col-md-4"
                value="{{ $project->phone }}"
            />
            <x-forms.input
                name="email"
                label="Email"
                class="col-6 col-md-4"
                value="{{ $project->email }}"
            />

            @if($project->logo && file_exists(storage_path('app/public/' . $project->logo)))
                <div class="col-6 col-md-4">
                    <label for="logo">Logo</label>
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $project->logo) }}"
                             alt="Logo"
                             class="img-thumbnail"
                             style="max-width: 100px; max-height: 100px;">
                    </div>
                    <x-forms.input
                        name="logo"
                        type="file"
                    />
                </div>
            @else
                <x-forms.input
                    class="col-6 col-md-4"
                    name="logo"
                    label="Logo"
                    type="file"
                />
            @endif
            <x-forms.textarea
                name="description"
                label="Description"
                class="col-12"
                value="{{ $project->description }}"
            />
        </div>
        <x-buttons.save
            container="edit-project-form"
            onclick="saveForm({element: this, modal: 'dynamic'})"
        />
    </form>
</section>
