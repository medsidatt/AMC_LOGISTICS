<x-layouts.main :title="__('Mon tableau de bord')">
    <style>
        .icon-big { font-size: 3rem !important; line-height: 1; }
        @media (max-width: 767.98px) {
            .icon-big { font-size: 2rem !important; }
            .h5 { font-size: 1rem; }
        }
    </style>

    @if(!$driver)
    <div class="alert alert-warning">
        <i class="la la-exclamation-triangle mr-2"></i>
        <strong>{{ __('Compte non lie') }}</strong> -
        {{ __('Votre compte utilisateur n\'est pas encore lie a un profil conducteur. Veuillez contacter votre responsable.') }}
    </div>
    @else

    {{-- Row 1: KPI Cards --}}
    <div class="row">
        {{-- Assigned Truck --}}
        <div class="col-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-truck text-primary mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __('Mon camion') }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $truck?->matricule ?? __('Non assigne') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- My Trips This Month --}}
        <div class="col-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-route text-success mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __('Mes voyages (mois)') }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $myTripsMonth }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- My Tonnage This Month --}}
        <div class="col-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-weight text-info mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __('Mon tonnage (mois)') }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ number_format($myTonnageMonth, 0, '', ' ') }} kg</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Today's Checklist Status --}}
        <div class="col-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100 {{ $todayChecklist ? 'checklist-done' : 'checklist-pending' }}">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la {{ $todayChecklist ? 'la-check-circle text-success' : 'la-clock text-warning' }} mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __("Checklist aujourd'hui") }}</div>
                        <div class="h6 mb-0 font-weight-bold">
                            {{ $todayChecklist ? __('Soumis') : __('En attente') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Action: Submit Checklist --}}
    @if(!$todayChecklist && $truck)
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('drivers.checklist-page') }}" class="btn btn-primary btn-lg w-100">
                <i class="la la-check-square mr-2"></i>
                {{ __('Soumettre le checklist quotidien') }}
            </a>
        </div>
    </div>
    @endif

    <div class="row">
        {{-- Left: Recent Trips --}}
        <div class="col-12 col-lg-7 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <i class="la la-route mr-1"></i>
                    <strong>{{ __('Mes derniers voyages') }}</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Camion') }}</th>
                                <th>{{ __('Fournisseur') }}</th>
                                <th>{{ __('Poids Net') }}</th>
                                <th>{{ __('Ecart') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recentTrips as $trip)
                                <tr>
                                    <td>{{ $trip->provider_date }}</td>
                                    <td>{{ $trip->truck?->matricule ?? '-' }}</td>
                                    <td>{{ $trip->provider?->name ?? '-' }}</td>
                                    <td>{{ number_format($trip->provider_net_weight ?? 0, 0, '', ' ') }}</td>
                                    <td>
                                        @php $gap = $trip->gap ?? 0; @endphp
                                        <span class="badge badge-{{ abs($gap) > 150 ? 'danger' : (abs($gap) > 50 ? 'warning' : 'success') }}">
                                            {{ number_format($gap, 0, '', ' ') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="la la-inbox" style="font-size: 1.5rem;"></i>
                                        <p class="mb-0">{{ __('Aucun voyage enregistre') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Checklist History --}}
        <div class="col-12 col-lg-5 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <i class="la la-clipboard-list mr-1"></i>
                    <strong>{{ __('Historique checklists') }}</strong>
                </div>
                <div class="card-body p-0">
                    @forelse($checklistHistory as $checklist)
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div>
                            <strong>{{ \Carbon\Carbon::parse($checklist->checklist_date)->format('d/m/Y') }}</strong>
                            <div class="small text-muted">
                                {{ __('Carburant') }}: {{ $checklist->fuel_level }}
                                @if($checklist->fuel_refill) <span class="badge badge-info badge-sm">{{ __('Rempli') }}</span> @endif
                            </div>
                        </div>
                        <div>
                            @if($checklist->issues->count() > 0)
                                <span class="badge badge-danger">{{ $checklist->issues->count() }} {{ __('probleme(s)') }}</span>
                            @else
                                <span class="badge badge-success">{{ __('OK') }}</span>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted py-4">
                        <i class="la la-clipboard" style="font-size: 2rem;"></i>
                        <p class="mb-0">{{ __('Aucun checklist soumis') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @endif
</x-layouts.main>
