<div id="create-truck-form">
    <form action="{{ route('trucks.store') }}" method="post">
        @csrf
        <div class="row">
            <x-forms.input
                class="col-md-6"
                name="matricule"
                type="text"
                :label="__('Matricule')"
                required
            />
            <x-forms.select
                class="col-md-6"
                name="transporter_id"
                :label="__('Transporteur')"
                :options="$transporters"
                :data-placeholder="__('Sélectionner un transporteur')"
                required
            />
            <x-forms.input
                class="col-md-6"
                name="km_maintenance_interval"
                type="number"
                :label="__('Intervalle Maintenance (KM)')"
                value="{{ \App\Models\Truck::MAX_KM_BEFORE_MAINTENANCE }}"
                required
            />
        </div>
        <x-buttons.save
            container="create-truck-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>

