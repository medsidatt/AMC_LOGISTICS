
    <div id="create-driver-form">
        <form action="{{ route('drivers.store') }}" method="post">
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

                <div class="col-12 mt-2">
                    <label class="form-check d-flex align-items-start gap-2">
                        <input type="hidden" name="whatsapp_opt_in" value="0">
                        <input class="form-check-input mt-1" type="checkbox" name="whatsapp_opt_in" value="1">
                        <span>
                            <strong>{{ __('Consentement WhatsApp') }}</strong><br>
                            <small class="text-muted">
                                {{ __("Le chauffeur consent à recevoir des notifications WhatsApp pour la programmation des rotations.") }}
                            </small>
                        </span>
                    </label>
                </div>
            </div>

            <x-buttons.save
                container="create-driver-form"
                onclick="saveForm({ element: this, modal: 'dynamic'})"
            />
        </form>
    </div>


