<div class="">
    <h4 class="mb-2">
        <i class="fas fa-route text-primary"></i>
        Détails Transport Tracking – {{ $tracking->reference }}
    </h4>

    <div class="alert alert-info py-2">
        <strong><i class="fas fa-truck-moving"></i> Camion:</strong>
        {{ $tracking->truck?->matricule ?? '—' }}
        &nbsp; | &nbsp;
        <strong><i class="fas fa-user"></i> Chauffeur:</strong>
        {{ $tracking->driver?->name ?? '—' }}
    </div>

    <div class="row">
        <div class="col">
            {{-- ===================== PROVIDER CARD ===================== --}}
            <div class="card mb-2 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-industry"></i> Données Fournisseur
                </div>
                <div class="card-body p-2">
                    <table class="table table-striped mb-2">
                        <tr><th>Date</th><td>{{ $tracking->provider_date/*?->format('d/m/Y')*/ ?? '—' }}</td></tr>
                        <tr><th>Ticket</th><td>{{ $tracking->provider_ticket ?? '—' }}</td></tr>
                        <tr><th>Poids Brut</th><td>{{ $tracking->provider_gross_weight ?? '—' }} T</td></tr>
                        <tr><th>Poids Tare</th><td>{{ $tracking->provider_tare_weight ?? '—' }} T</td></tr>
                        <tr><th>Poids Net</th><td><strong>{{ $tracking->provider_net_weight ?? '—' }} T</strong></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col">
            {{-- ===================== CLIENT CARD ===================== --}}
            <div class="card mb-2 shadow-sm">
                <div class="card-header bg-warning">
                    <i class="fas fa-handshake"></i> Données Client
                </div>
                <div class="card-body p-2">
                    <table class="table table-striped mb-2">
                        <tr><th>Date</th><td>{{ $tracking->client_date/*?->format('d/m/Y')*/ ?? '—' }}</td></tr>
                        <tr><th>Ticket</th><td>{{ $tracking->client_ticket ?? '—' }}</td></tr>
                        <tr><th>Poids Brut</th><td>{{ $tracking->client_gross_weight ?? '—' }} T</td></tr>
                        <tr><th>Poids Tare</th><td>{{ $tracking->client_tare_weight ?? '—' }} T</td></tr>
                        <tr><th>Poids Net</th><td><strong>{{ $tracking->client_net_weight ?? '—' }} T</strong></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== GAP ===================== --}}
    @php
        $gap = ($tracking->provider_net_weight ?? 0) - ($tracking->client_net_weight ?? 0);
    @endphp

    <div class="mb-2">
        <span class="fw-bold">
            <i class="fas fa-exclamation-triangle text-danger"></i>
            Écart:
        </span>

        @if($gap == 0)
            <span class="badge bg-success">0 (Pas d'écart)</span>
        @elseif($gap > 0)
            <span class="badge bg-danger">{{ $gap }} T Perte</span>
        @else
            <span class="badge bg-warning">{{ abs($gap) }} T Gain</span>
        @endif
    </div>


    {{-- ===================== FILES TABLE ===================== --}}{{--
    <h5 class="mt-2"><i class="fas fa-file-alt"></i> Fichiers</h5>
    <table class="table table-bordered">
        <thead class="table-light">
        <tr>
            <th>Type</th>
            <th>Fichier</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Fournisseur</td>
            <td>{{ $tracking->provider_file ? basename($tracking->provider_file) : '—' }}</td>
            <td>
                @if($tracking->provider_file && Storage::disk('public')->exists($tracking->provider_file))
                    <a class="btn btn-sm btn-primary" target="_blank" href="--}}{{--{{ route('transport_tracking.provider_file', $tracking->id) }}--}}{{--">
                        <i class="fas fa-eye"></i> Voir
                    </a>
                @endif
            </td>
        </tr>

        <tr>
            <td>Client</td>
            <td>{{ $tracking->client_file ? basename($tracking->client_file) : '—' }}</td>
            <td>
                @if($tracking->client_file && Storage::disk('public')->exists($tracking->client_file))
                    <a class="btn btn-sm btn-primary" target="_blank" href="--}}{{--{{ route('transport_tracking.client_file', $tracking->id) }}--}}{{--">
                        <i class="fas fa-eye"></i> Voir
                    </a>
                @endif
            </td>
        </tr>
        </tbody>
    </table>--}}

    {{-- ===================== FILES TABLE ===================== --}}
    <h5 class="mt-2"><i class="fas fa-file-alt"></i> Fichiers</h5>
    @if($tracking->documents->count() > 0)
        <table class="table table-bordered">
            <thead class="table-light">
            <tr>
                <th>Type</th>
                <th>Fichier</th>
                <th>Taille</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($tracking->documents as $document)
                <tr>
                    <td>
                        @if($document->type === 'provider')
                            <i class="fas fa-truck-loading text-primary"></i> Fournisseur
                        @elseif($document->type === 'client')
                            <i class="fas fa-user-tag text-warning"></i> Client
                        @elseif($document->type === 'commune')
                            <i class="fas fa-building text-info"></i> Commune
                        @else
                            <i class="fas fa-file text-secondary"></i> Autre
                        @endif
                    </td>
                    <td>{{ $document->original_name ?? basename($document->file_path) }}</td>
                    <td>{{ $document->size ? number_format($document->size / 1024, 2) . ' KB' : '—' }}</td>
                    <td>
                        @if(Storage::disk('public')->exists($document->file_path))
                            <button class="btn btn-sm btn-primary"
                                    onclick="showModal({
                                        title: '{{ addslashes($document->original_name ?? basename($document->file_path)) }}',
                                        route: '{{ Storage::url($document->file_path) }}',
                                        size: 'xl',
                                        file: true,
                                        modalId: 'file-preview-modal'
                                    })">
                                <i class="fas fa-eye"></i> Voir
                            </button>
                        @else
                            <span class="text-muted">Fichier introuvable</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        
        {{-- Combined PDF Button --}}
        @if($tracking->documents->where('mime_type', 'application/pdf')->count() > 0)
            <div class="mt-2">
                <button class="btn btn-success"
                        onclick="showModal({
                            title: 'Tous les fichiers - {{ addslashes($tracking->reference) }}',
                            route: '{{ route("transport_tracking.preview-files", $tracking->id) }}',
                            size: 'xl',
                            modalId: 'file-preview-modal'
                        })">
                    <i class="fas fa-file-pdf"></i> Voir tous les fichiers
                </button>
            </div>
        @endif
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Aucun fichier associé à ce transport tracking.
        </div>
    @endif


    {{-- ===================== RELATED HISTORY ===================== --}}
    <h5 class="mt-3"><i class="fas fa-history"></i> Historique</h5>

    <ul class="list-group">
        @foreach($tracking->truck?->transportTrackings()->latest()->take(5)->get() ?? [] as $item)
            <li class="list-group-item">
                <i class="fas fa-route text-primary"></i>
                {{ $item->reference }} – {{--{{ $item->date?->format('d/m/Y') }}--}}
            </li>
        @endforeach
    </ul>

</div>



