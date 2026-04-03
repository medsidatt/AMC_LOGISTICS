<div class="truck-details">
    {{-- Header with Truck Info --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="fas fa-truck text-primary"></i>
            Camion – {{ $truck->matricule }}
        </h4>
    </div>

    <div class="row">
        {{-- Left Column: Truck Info & Maintenance Status --}}
        <div class="col-md-6">
            {{-- Basic Info Card --}}
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informations Générales</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <tr>
                            <th style="width: 40%">Matricule</th>
                            <td><strong>{{ $truck->matricule }}</strong></td>
                        </tr>
                        <tr>
                            <th>Transporteur</th>
                            <td>{{ $truck->transporter->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Marque</th>
                            <td>{{ $truck->brand ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Modèle</th>
                            <td>{{ $truck->model ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Type</th>
                            <td>{{ $truck->type ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Total Rotations</th>
                            <td><span class="badge bg-info">{{ $truck->total_rotations ?? 0 }}</span></td>
                        </tr>
                        <tr>
                            <th>Kilométrage Total</th>
                            <td><span class="badge bg-primary">{{ number_format($truck->total_kilometers, 0) }} km</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column: Maintenance History --}}
        <div class="col-md-6">
            {{-- Maintenance History Card --}}
            <div class="card mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Historique des Maintenances</h6>
                    <span class="badge bg-secondary">{{ $maintenances->count() }}</span>
                </div>
                <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                    @if($maintenances->count() > 0)
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Km au moment</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($maintenances as $maintenance)
                                    <tr>
                                        <td>
                                            <i class="fas fa-calendar text-muted"></i>
                                            {{ \Carbon\Carbon::parse($maintenance->maintenance_date)->format('d/m/Y') }}
                                        </td>
                                        <td>
                                            @if($maintenance->kilometers_at_maintenance)
                                                <span class="badge bg-light text-dark border">{{ number_format($maintenance->kilometers_at_maintenance, 0) }} km</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $maintenance->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">Aucune maintenance enregistrée</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-bolt"></i> Actions Rapides</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button"
                                class="btn btn-outline-primary"
                                onclick="showModal({
                                    title: 'Ajouter Maintenance - {{ $truck->matricule }}',
                                    route: '{{ route('trucks.maintenances.create', $truck->id) }}',
                                    size: 'md'
                                })">
                            <i class="fas fa-plus"></i> Ajouter Maintenance
                        </button>
                        <a class="btn btn-outline-secondary"
                           href="{{ route('trucks.edit-page', $truck->id) }}">
                            <i class="fas fa-edit"></i> Modifier Camion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        {{-- Rotation-Based Maintenance --}}
        <div class="col-md-6">
            @php
                $rotationLevel = $maintenanceInfo['rotations']['level'];
                $rotationLevelClass = match($rotationLevel) {
                    'red' => 'danger',
                    'yellow' => 'warning',
                    default => 'success'
                };
            @endphp
            <div class="card mb-3 h-100">
                <div class="card-header bg-{{ $rotationLevelClass }} text-{{ $rotationLevelClass === 'warning' ? 'dark' : 'white' }}">
                    <h6 class="mb-0"><i class="fas fa-sync-alt"></i> Maintenance par Rotations</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <h3 class="mb-0 text-{{ $rotationLevelClass }}">{{ $maintenanceInfo['rotations']['since_maintenance'] }}</h3>
                                <small class="text-muted d-block">Depuis dernière</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <h3 class="mb-0 text-primary">{{ $maintenanceInfo['rotations']['remaining'] }}</h3>
                                <small class="text-muted d-block">Restantes</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <h3 class="mb-0">{{ \App\Models\Truck::MAX_ROTATIONS_BEFORE_MAINTENANCE }}</h3>
                                <small class="text-muted d-block">Intervalle Max</small>
                            </div>
                        </div>
                    </div>

                    @php
                        $rotationProgress = min(100, ($maintenanceInfo['rotations']['since_maintenance'] / \App\Models\Truck::MAX_ROTATIONS_BEFORE_MAINTENANCE) * 100);
                    @endphp
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Progression</small>
                            <small>{{ number_format($rotationProgress, 0) }}%</small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-{{ $rotationLevelClass }}" role="progressbar" style="width: {{ $rotationProgress }}%" aria-valuenow="{{ $maintenanceInfo['rotations']['since_maintenance'] }}" aria-valuemin="0" aria-valuemax="{{ \App\Models\Truck::MAX_ROTATIONS_BEFORE_MAINTENANCE }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kilometer-Based Maintenance --}}
        <div class="col-md-6">
            @php
                $kmLevel = $maintenanceInfo['kilometers']['level'];
                $kmLevelClass = match($kmLevel) {
                    'red' => 'danger',
                    'yellow' => 'warning',
                    default => 'success'
                };
            @endphp
            <div class="card mb-3 h-100">
                <div class="card-header bg-{{ $kmLevelClass }} text-{{ $kmLevelClass === 'warning' ? 'dark' : 'white' }}">
                    <h6 class="mb-0"><i class="fas fa-road"></i> Maintenance par Kilomètres</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">Compteur Actuel</small>
                                <span class="h5 mb-0">{{ number_format($maintenanceInfo['kilometers']['current_total'], 0) }} km</span>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Prochaine Maintenance à</small>
                                <span class="h5 mb-0 text-primary">{{ number_format($maintenanceInfo['kilometers']['next_maintenance_at'], 0) }} km</span>
                            </div>
                        </div>
                    </div>

                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <h4 class="mb-0 text-{{ $kmLevelClass }}">{{ number_format($maintenanceInfo['kilometers']['since_maintenance'], 0) }}</h4>
                                <small class="text-muted d-block">Parcourus</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <h4 class="mb-0 text-primary">{{ number_format($maintenanceInfo['kilometers']['remaining'], 0) }}</h4>
                                <small class="text-muted d-block">Restants</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <h4 class="mb-0">{{ number_format($maintenanceInfo['kilometers']['interval'], 0) }}</h4>
                                <small class="text-muted d-block">Intervalle</small>
                            </div>
                        </div>
                    </div>

                    @php
                        $kmProgress = $maintenanceInfo['kilometers']['interval'] > 0
                            ? min(100, ($maintenanceInfo['kilometers']['since_maintenance'] / $maintenanceInfo['kilometers']['interval']) * 100)
                            : 0;
                    @endphp
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Progression vers maintenance</small>
                            <small>{{ number_format($kmProgress, 0) }}%</small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-{{ $kmLevelClass }}" role="progressbar" style="width: {{ $kmProgress }}%" aria-valuenow="{{ $maintenanceInfo['kilometers']['since_maintenance'] }}" aria-valuemin="0" aria-valuemax="{{ $maintenanceInfo['kilometers']['interval'] }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($maintenanceInfo['maintenance_types']))
        <div class="card mt-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-list-check"></i> Maintenance par Type (KM)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Intervalle (km)</th>
                            <th>Prochaine Maintenance (km)</th>
                            <th>Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($maintenanceInfo['maintenance_types'] as $profile)
                            @php
                                $badgeClass = match($profile['status']) {
                                    'red' => 'danger',
                                    'yellow' => 'warning',
                                    default => 'success',
                                };
                            @endphp
                            <tr>
                                <td>{{ ucfirst($profile['type']) }}</td>
                                <td>{{ number_format($profile['interval_km'], 0) }}</td>
                                <td>{{ number_format($profile['next_maintenance_km'], 0) }}</td>
                                <td><span class="badge bg-{{ $badgeClass }}">{{ strtoupper($profile['status']) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Recent Transport Trackings --}}
    <div class="card mt-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-route"></i> Dernières Rotations (Transport Trackings)</h6>
            <span class="badge bg-primary">{{ $recentTrackings->count() }} affichées</span>
        </div>
        <div class="card-body p-0">
            @if($recentTrackings->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Date Client</th>
                                <th>Date Fournisseur</th>
                                <th>Chauffeur</th>
                                <th>Fournisseur</th>
                                <th>Produit</th>
                                <th>Poids Net Client</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTrackings as $tracking)
                                <tr>
                                    <td>
                                        <a href="javascript:void(0)"
                                           onclick="showModal({
                                               title: 'Détails {{ $tracking->reference }}',
                                               route: '{{ route('transport_tracking.show', $tracking->id) }}',
                                               size: 'xl'
                                           })"
                                           class="text-primary fw-bold">
                                            {{ $tracking->reference }}
                                        </a>
                                    </td>
                                    <td>{{ $tracking->client_date ? \Carbon\Carbon::parse($tracking->client_date)->format('d/m/Y') : '—' }}</td>
                                    <td>{{ $tracking->provider_date ? \Carbon\Carbon::parse($tracking->provider_date)->format('d/m/Y') : '—' }}</td>
                                    <td>{{ $tracking->driver->name ?? '—' }}</td>
                                    <td>{{ $tracking->provider->name ?? '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $tracking->product ?? '—' }}</span></td>
                                    <td>{{ $tracking->client_net_weight ? number_format($tracking->client_net_weight, 2) . ' T' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-truck-loading fa-2x mb-2"></i>
                    <p class="mb-0">Aucune rotation enregistrée pour ce camion</p>
                </div>
            @endif
        </div>
    </div>
</div>
