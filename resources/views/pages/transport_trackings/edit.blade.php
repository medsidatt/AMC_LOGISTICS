<div id="edit-transport_tracking-form">
    <form action="{{ route('transport_tracking.update', $transportTracking->id) }}"
          method="POST"
          enctype="multipart/form-data">

        @csrf
        @method('PUT')

        {{-- ================== BASIC INFORMATION ================== --}}
        <div class="card mb-2">
            <div class="card-body">
                <h5 class="mb-3">{{ __('Informations générales') }}</h5>

                <div class="row">
                    <x-forms.select class="col-md-6"
                                    name="provider_id"
                                    :label="__('Fournisseur')"
                                    :options="$providers"
                                    :data-placeholder="__('Sélectionner un fournisseur')"
                                    :value="$transportTracking->provider_id"
                    />
                    <x-forms.select class="col-md-6"
                                    name="truck_id"
                                    :label="__('Camion')"
                                    :options="$trucks"
                                    :label-field="'matricule'"
                                    data-tags="true"
                                    :data-fields="[
                                        'driver' => 'driver_id',
                                        'transporter' => 'transporter_id'
                                    ]"
                                    :value="$transportTracking->truck_id"
                                    onchange="selectDriver(this)"
                    />

                </div>
                <div class="row">

                    <x-forms.select class="col-md-6"
                                    name="transporter_id"
                                    :label="__('Transporteur')"
                                    :options="$transporters"
                                    :data-placeholder="__('Sélectionner un transporter')"
                                    :value="$transportTracking?->truck?->transporter_id"
                    />

                    <x-forms.select class="col-md-6"
                                    name="driver_id"
                                    :label="__('Chauffeur')"
                                    :options="$drivers"
                                    data-tags="true"
                                    :data-placeholder="__('Sélectionner un chauffeur')"
                                    :value="$transportTracking->driver_id"
                    />
                </div>
            </div>
        </div>

        {{-- ================== PRODUCT & FILES ================== --}}
        <div class="card mb-2">
            <div class="card-body">
                <h5 class="mb-3">{{ __('Produit & Base') }}</h5>

                <div class="row">
                    <x-forms.select class="col-md-4"
                                    name="product"
                                    :label="__('Produit')"
                                    :options="$products"
                                    :data-placeholder="__('Sélectionner un produit')"
                                    :value="$transportTracking->product"
                    />

                    <x-forms.select class="col-md-4"
                                    name="base"
                                    :label="__('Base')"
                                    :options="$bases"
                                    :data-placeholder="__('Sélectionner une base')"
                                    :value="$transportTracking->base"
                    />

                    <div class="col-md-4">
                        <label class="form-label">{{ __('Documents') }}</label>
                        <input type="file" 
                               name="files[]" 
                               class="form-control" 
                               multiple 
                               accept=".pdf,.jpg,.jpeg,.png"
                               id="files-input-edit">
                        <small class="text-muted">Vous pouvez sélectionner plusieurs fichiers (PDF, JPG, PNG)</small>
                        
                        {{-- Existing Files --}}
                        @if($transportTracking->documents->count() > 0)
                            <div class="mt-3">
                                <label class="form-label small">{{ __('Fichiers existants') }}</label>
                                <div class="list-group" id="existing-files-list">
                                    @foreach($transportTracking->documents as $document)
                                        <div class="list-group-item d-flex justify-content-between align-items-center" data-document-id="{{ $document->id }}">
                                            <div class="d-flex align-items-center">
                                                @if($document->mime_type === 'application/pdf')
                                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                                @else
                                                    <i class="fas fa-file-image text-primary me-2"></i>
                                                @endif
                                                <span class="small">{{ $document->original_name ?? basename($document->file_path) }}</span>
                                                <span class="badge bg-secondary ms-2">
                                                    @if($document->type === 'provider')
                                                        Fournisseur
                                                    @elseif($document->type === 'client')
                                                        Client
                                                    @elseif($document->type === 'commune')
                                                        Commune
                                                    @else
                                                        Autre
                                                    @endif
                                                </span>
                                            </div>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="deleteDocument({{ $transportTracking->id }}, {{ $document->id }}, this)">
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

        {{-- ================== DELIVERY DOCUMENTS ================== --}}
        <div class="card">
            <div class="card-body">
                <h5 class="mb-2">{{ __('Documents de livraison') }}</h5>

                <div class="row">

                    {{-- Fournisseur --}}
                    <div class="col-lg-4 col-md mb-4">
                        <h6 class="text-primary">{{ __('Fournisseur') }}</h6>

                        <x-forms.input name="provider_date"
                                       type="date"
                                       :label="__('Date')"
                                       :value="$transportTracking->provider_date"
                        />

                        <x-forms.input name="provider_gross_weight"
                                       :label="__('Poids brut')"
                                       :value="$transportTracking->provider_gross_weight"
                        />

                        <x-forms.input name="provider_tare_weight"
                                       :label="__('Poids tare')"
                                       :value="$transportTracking->provider_tare_weight"
                        />

                        <x-forms.input name="provider_net_weight"
                                       :label="__('Poids net')"
                                       :value="$transportTracking->provider_net_weight"
                        />
                    </div>

                    {{-- Client --}}
                    <div class="col-lg-4 col-md mb-4">
                        <h6 class="text-primary">{{ __('Client') }}</h6>

                        <x-forms.input name="client_date"
                                       type="date"
                                       :label="__('Date')"
                                       :value="$transportTracking->client_date"
                        />

                        <x-forms.input name="client_gross_weight"
                                       :label="__('Poids brut')"
                                       :value="$transportTracking->client_gross_weight"
                        />

                        <x-forms.input name="client_tare_weight"
                                       :label="__('Poids tare')"
                                       :value="$transportTracking->client_tare_weight"
                        />

                        <x-forms.input name="client_net_weight"
                                       :label="__('Poids net')"
                                       :value="$transportTracking->client_net_weight"
                        />
                    </div>

                    {{-- Commune --}}
                    <div class="col-lg-4 col-md mb-4">
                        <h6 class="text-primary">{{ __('Commune') }}</h6>

                        <x-forms.input name="commune_date"
                                       type="date"
                                       :label="__('Date')"
                                       :value="$transportTracking->commune_date"
                        />

                        <x-forms.input name="commune_weight"
                                       :label="__('Poids')"
                                       :value="$transportTracking->commune_weight"
                        />
                    </div>

                </div>
            </div>
        </div>

        {{-- ================== ACTIONS ================== --}}
        <div class="mt-4">
            <x-buttons.save
                container="edit-transport_tracking-form"
                onclick="saveForm({ element: this, modal: 'dynamic' })"
            />
        </div>

    </form>
</div>

<script>
function deleteDocument(trackingId, documentId, button) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) {
        return;
    }
    
    fetch(`{{ url('/transport_tracking') }}/${trackingId}/document/${documentId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the file item from the list
            button.closest('.list-group-item').remove();
            
            // Show success message
            if (typeof showNotification === 'function') {
                showNotification('Document supprimé avec succès', 'success');
            } else {
                alert('Document supprimé avec succès');
            }
            
            // If no files left, hide the list container
            const filesList = document.getElementById('existing-files-list');
            if (filesList && filesList.children.length === 0) {
                filesList.closest('.mt-3').style.display = 'none';
            }
        } else {
            alert('Erreur lors de la suppression du document');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la suppression du document');
    });
}
</script>
