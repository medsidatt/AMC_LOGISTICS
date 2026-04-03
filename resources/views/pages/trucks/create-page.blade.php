<x-layouts.main :title="__('Ajouter Camion')">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('trucks.store') }}" method="POST">
                @csrf
                <div class="row">
                    <x-forms.input class="col-md-6" name="matricule" type="text" :label="__('Matricule')" required />
                    <x-forms.select class="col-md-6" name="transporter_id" :label="__('Transporteur')" :options="$transporters"
                        :data-placeholder="__('Selectionner un transporteur')" required />
                    <x-forms.input class="col-md-6" name="km_maintenance_interval" type="number"
                        :label="__('Intervalle Maintenance (KM)')" value="{{ \App\Models\Truck::MAX_KM_BEFORE_MAINTENANCE }}" required />
                </div>
                <div class="d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('trucks.index') }}" class="btn btn-light mr-1">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Enregistrer') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.main>
