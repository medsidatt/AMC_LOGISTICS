<div id="edit-truck-form">
    <form action="{{ route('trucks.update', $truck->id) }}" method="post">
        @csrf
        @method('PUT')
        <div class="row">
            <x-forms.input
                class="col-md-6"
                name="matricule"
                type="text"
                :label="__('Matricule')"
                required
                value="{{ $truck->matricule }}"
            />
            <x-forms.select
                class="col-md-6"
                name="transporter_id"
                :label="__('Transporteur')"
                :options="$transporters"
                :data-placeholder="__('Sélectionner un transporteur')"
                required
                value="{{ $truck->transporter_id }}"
            />
            <x-forms.input
                class="col-md-6"
                name="km_maintenance_interval"
                type="number"
                :label="__('Intervalle Maintenance (KM)')"
                value="{{ $truck->km_maintenance_interval ?? \App\Models\Truck::MAX_KM_BEFORE_MAINTENANCE }}"
                required
            />
        </div>
        <x-buttons.save
            container="edit-truck-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>



