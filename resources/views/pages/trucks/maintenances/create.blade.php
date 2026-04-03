<div id="create-truck-maintenance-form">
    <form action="{{ route('trucks.maintenances.store', $truck->id) }}" method="post">
        @csrf
        <div class="row">
            <div class="col-md-12 mb-1">
                <label for="maintenance_type">{{ __('Type de maintenance') }}</label>
                <select name="maintenance_type" id="maintenance_type" class="form-control">
                    @foreach(config('maintenance.types', []) as $type => $definition)
                        <option value="{{ $type }}">{{ $definition['label'] ?? ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <x-forms.input
                class="col-md-12"
                name="date"
                type="date"
                :label="__('Date de maintenance')"
                required
            />
            <x-forms.input
                class="col-md-12"
                name="kilometers_at_maintenance"
                type="number"
                :label="__('Compteur KM au moment de la maintenance')"
                value="{{ (int) ($truck->total_kilometers ?? 0) }}"
                required
            />
            <x-forms.textarea
                class="col-md-12"
                name="notes"
                :label="__('Notes')"
                rows="2"
            />
        </div>
        <x-buttons.save
            container="create-truck-maintenance-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>

