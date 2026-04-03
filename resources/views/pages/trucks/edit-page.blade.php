<x-layouts.main :title="__('Modifier Camion')">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('trucks.update', $truck->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <x-forms.input class="col-md-6" name="matricule" type="text" :label="__('Matricule')" required value="{{ $truck->matricule }}" />
                    <x-forms.select class="col-md-6" name="transporter_id" :label="__('Transporteur')" :options="$transporters"
                        :data-placeholder="__('Selectionner un transporteur')" required value="{{ $truck->transporter_id }}" />
                    <x-forms.input class="col-md-6" name="km_maintenance_interval" type="number"
                        :label="__('Intervalle Maintenance (KM)')" value="{{ $truck->km_maintenance_interval ?? \App\Models\Truck::MAX_KM_BEFORE_MAINTENANCE }}" required />
                </div>
                <div class="d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('trucks.index') }}" class="btn btn-light mr-1">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Mettre a jour') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.main>
