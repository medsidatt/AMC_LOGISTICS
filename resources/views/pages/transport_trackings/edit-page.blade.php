<x-layouts.main :title="__('Modifier stock')">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('transport_tracking.update', $transportTracking->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="mb-3">{{ __('Informations generales') }}</h5>
                        <div class="row">
                            <x-forms.select class="col-md-6" name="provider_id" :label="__('Fournisseur')" :options="$providers"
                                :data-placeholder="__('Selectionner un fournisseur')" :value="$transportTracking->provider_id" />
                            <x-forms.select class="col-md-6" name="truck_id" :label="__('Camion')" :options="$trucks"
                                :label-field="'matricule'" data-tags="true"
                                :data-fields="['driver' => 'driver_id', 'transporter' => 'transporter_id']"
                                :value="$transportTracking->truck_id" onchange="selectDriver(this)" />
                        </div>
                        <div class="row">
                            <x-forms.select class="col-md-6" name="transporter_id" :label="__('Transporteur')" :options="$transporters"
                                :data-placeholder="__('Selectionner un transporteur')" :value="$transportTracking?->truck?->transporter_id" />
                            <x-forms.select class="col-md-6" name="driver_id" :label="__('Chauffeur')" :options="$drivers"
                                data-tags="true" :data-placeholder="__('Selectionner un chauffeur')" :value="$transportTracking->driver_id" />
                        </div>
                    </div>
                </div>

                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="mb-3">{{ __('Produit et Base') }}</h5>
                        <div class="row">
                            <x-forms.select class="col-md-4" name="product" :label="__('Produit')" :options="$products"
                                :data-placeholder="__('Selectionner un produit')" :value="$transportTracking->product" />
                            <x-forms.select class="col-md-4" name="base" :label="__('Base')" :options="$bases"
                                :data-placeholder="__('Selectionner une base')" :value="$transportTracking->base" />
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Documents') }}</label>
                                <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Vous pouvez selectionner plusieurs fichiers (PDF, JPG, PNG)</small>
                                @if($transportTracking->documents->count() > 0)
                                    <div class="mt-3">
                                        <label class="form-label small">{{ __('Fichiers existants') }}</label>
                                        <div class="list-group" id="existing-files-list">
                                            @foreach($transportTracking->documents as $document)
                                                <div class="list-group-item d-flex justify-content-between align-items-center" data-document-id="{{ $document->id }}">
                                                    <div class="d-flex align-items-center">
                                                        <span class="small">{{ $document->original_name ?? basename($document->file_path) }}</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-danger delete-doc-btn"
                                                        data-tracking-id="{{ $transportTracking->id }}"
                                                        data-document-id="{{ $document->id }}"
                                                    >
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="mb-4">{{ __('Documents de livraison') }}</h5>
                        <div class="row">
                            <div class="col-lg-4 col-md mb-4">
                                <h6 class="text-primary">{{ __('Fournisseur') }}</h6>
                                <x-forms.input name="provider_date" type="date" :label="__('Date')" :value="$transportTracking->provider_date" />
                                <x-forms.input name="provider_gross_weight" :label="__('Poids brut')" :value="$transportTracking->provider_gross_weight" />
                                <x-forms.input name="provider_tare_weight" :label="__('Poids tare')" :value="$transportTracking->provider_tare_weight" />
                                <x-forms.input name="provider_net_weight" :label="__('Poids net')" :value="$transportTracking->provider_net_weight" />
                            </div>
                            <div class="col-lg-4 col-md mb-4">
                                <h6 class="text-primary">{{ __('Client') }}</h6>
                                <x-forms.input name="client_date" type="date" :label="__('Date')" :value="$transportTracking->client_date" />
                                <x-forms.input name="client_gross_weight" :label="__('Poids brut')" :value="$transportTracking->client_gross_weight" />
                                <x-forms.input name="client_tare_weight" :label="__('Poids tare')" :value="$transportTracking->client_tare_weight" />
                                <x-forms.input name="client_net_weight" :label="__('Poids net')" :value="$transportTracking->client_net_weight" />
                            </div>
                            <div class="col-lg-4 col-md mb-4">
                                <h6 class="text-primary">{{ __('Commune') }}</h6>
                                <x-forms.input name="commune_date" type="date" :label="__('Date')" :value="$transportTracking->commune_date" />
                                <x-forms.input name="commune_weight" :label="__('Poids')" :value="$transportTracking->commune_weight" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('transport_tracking.index') }}" class="btn btn-light mr-1">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Mettre a jour') }}</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            const transportTrackingBaseUrl = "{{ url('/transport_tracking') }}";

            function deleteDocument(trackingId, documentId, button) {
                if (!confirm('Etes-vous sur de vouloir supprimer ce document ?')) return;
                fetch(transportTrackingBaseUrl + '/' + trackingId + '/document/' + documentId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        const item = button.closest('.list-group-item');
                        if (item) {
                            item.remove();
                        }
                    }
                });
            }

            document.querySelectorAll('.delete-doc-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    deleteDocument(
                        this.dataset.trackingId,
                        this.dataset.documentId,
                        this
                    );
                });
            });
        </script>
    @endpush
</x-layouts.main>
