<x-layouts.main :title="__('Ajouter stock')">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('transport_tracking.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="mb-3">{{ __('Informations generales') }}</h5>
                        <div class="row">
                            <x-forms.select class="col-md-6" name="provider_id" :label="__('Fournisseur')" :options="$providers"
                                :data-placeholder="__('Selectionner un fournisseur')" />
                            <x-forms.select class="col-md-6" name="truck_id" :label="__('Camion')" :options="$trucks"
                                :label-field="'matricule'" data-tags="true"
                                :data-fields="['driver' => 'driver_id', 'transporter' => 'transporter_id']"
                                :data-placeholder="__('Selectionner un camion')" onchange="selectDriver(this)" required />
                        </div>
                        <div class="row">
                            <x-forms.select class="col-md-6" name="transporter_id" :label="__('Transporteur')" :options="$transporters"
                                :data-placeholder="__('Selectionner un transporteur')" />
                            <x-forms.select class="col-md-6" name="driver_id" :label="__('Chauffeur')" :options="$drivers"
                                data-tags="true" :data-placeholder="__('Selectionner un chauffeur')" required />
                        </div>
                    </div>
                </div>

                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="mb-3">{{ __('Produit et Base') }}</h5>
                        <div class="row">
                            <x-forms.select class="col-md-4" name="product" :label="__('Produit')" :options="$products"
                                :data-placeholder="__('Selectionner un produit')" required />
                            <x-forms.select class="col-md-4" name="base" :label="__('Base')" :options="$bases"
                                :data-placeholder="__('Selectionner une base')" required />
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Documents') }}</label>
                                <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png" id="files-input-create">
                                <small class="text-muted">Vous pouvez selectionner plusieurs fichiers (PDF, JPG, PNG)</small>
                                <div id="file-list-create" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="mb-4">{{ __('Documents de livraison') }}</h5>
                        <div class="row">
                            <div class="col-lg-4 col-md-6 mb-4">
                                <h6 class="text-primary">{{ __('Fournisseur') }}</h6>
                                <x-forms.input name="provider_date" type="date" :label="__('Date')" />
                                <x-forms.input name="provider_gross_weight" :label="__('Poids brut')" />
                                <x-forms.input name="provider_tare_weight" :label="__('Poids tare')" />
                                <x-forms.input name="provider_net_weight" :label="__('Poids net')" />
                            </div>
                            <div class="col-lg-4 col-md mb-4">
                                <h6 class="text-primary">{{ __('Client') }}</h6>
                                <x-forms.input name="client_date" type="date" :label="__('Date')" />
                                <x-forms.input name="client_gross_weight" :label="__('Poids brut')" />
                                <x-forms.input name="client_tare_weight" :label="__('Poids tare')" />
                                <x-forms.input name="client_net_weight" :label="__('Poids net')" />
                            </div>
                            <div class="col-lg-4 col-md mb-4">
                                <h6 class="text-primary">{{ __('Commune') }}</h6>
                                <x-forms.input name="commune_date" type="date" :label="__('Date')" />
                                <x-forms.input name="commune_weight" :label="__('Poids')" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('transport_tracking.index') }}" class="btn btn-light mr-1">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Enregistrer') }}</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('files-input-create')?.addEventListener('change', function(e) {
                const fileList = document.getElementById('file-list-create');
                if (!fileList) return;
                fileList.innerHTML = '';
                if (e.target.files.length > 0) {
                    const list = document.createElement('ul');
                    list.className = 'list-unstyled small';
                    Array.from(e.target.files).forEach(file => {
                        const li = document.createElement('li');
                        li.innerHTML = `<i class="fas fa-file me-1"></i> ${file.name} <span class="text-muted">(${(file.size / 1024).toFixed(2)} KB)</span>`;
                        list.appendChild(li);
                    });
                    fileList.appendChild(list);
                }
            });
        </script>
    @endpush
</x-layouts.main>
