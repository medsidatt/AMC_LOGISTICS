
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

            <div class="col-12 mt-2">
                <label class="form-check d-flex align-items-start gap-2">
                    <input type="hidden" name="whatsapp_opt_in" value="0">
                    <input class="form-check-input mt-1"
                           type="checkbox"
                           name="whatsapp_opt_in"
                           value="1"
                           {{ $driver->whatsapp_opt_in_at ? 'checked' : '' }}>
                    <span>
                        <strong>{{ __('Consentement WhatsApp') }}</strong>
                        @if($driver->whatsapp_opt_in_at)
                            <small class="text-success ms-2">
                                ({{ __('depuis le') }} {{ $driver->whatsapp_opt_in_at->format('d/m/Y') }})
                            </small>
                        @endif
                        <br>
                        <small class="text-muted">
                            {{ __("Le chauffeur consent à recevoir des notifications WhatsApp pour la programmation des rotations.") }}
                        </small>
                    </span>
                </label>
            </div>
        </div>

        <x-buttons.save
            container="edit-driver-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>




