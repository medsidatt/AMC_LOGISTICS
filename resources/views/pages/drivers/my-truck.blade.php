<x-layouts.main :title="__('Mon camion')">

    <div class="row">
        {{-- Truck Info Card --}}
        <div class="col-12 col-md-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <i class="la la-truck mr-1"></i>
                    <strong>{{ $truck->matricule }}</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 40%;">{{ __('Matricule') }}</td>
                            <td><strong>{{ $truck->matricule }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('Transporteur') }}</td>
                            <td>{{ $truck->transporter?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('Kilometres') }}</td>
                            <td>{{ number_format($truck->total_kilometers ?? 0, 0, '', ' ') }} km</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('Statut') }}</td>
                            <td>
                                @if($truck->is_active)
                                    <span class="badge badge-success">{{ __('Actif') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ __('Inactif') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('Mes voyages') }}</td>
                            <td><strong>{{ $myTripsCount }}</strong> {{ __('voyages') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Maintenance Info Card --}}
        <div class="col-12 col-md-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <i class="la la-wrench mr-1"></i>
                    <strong>{{ __('Maintenance') }}</strong>
                </div>
                <div class="card-body">
                    @php
                        $level = $truck->maintenanceLevelByType();
                        $levelClass = $level === 'red' ? 'danger' : ($level === 'yellow' ? 'warning' : 'success');
                        $levelText = $level === 'red' ? __('Maintenance requise') : ($level === 'yellow' ? __('Bientot due') : __('OK'));
                    @endphp
                    <div class="text-center mb-3">
                        <span class="badge badge-{{ $levelClass }} p-2" style="font-size: 1rem;">
                            <i class="la la-{{ $level === 'green' ? 'check-circle' : 'exclamation-triangle' }} mr-1"></i>
                            {{ $levelText }}
                        </span>
                    </div>

                    @if($truck->maintenances->isNotEmpty())
                    <h6 class="text-muted mt-3">{{ __('Derniere maintenance') }}</h6>
                    @php $lastMaint = $truck->maintenances->first(); @endphp
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">{{ __('Date') }}</td>
                            <td>{{ $lastMaint->maintenance_date?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('Type') }}</td>
                            <td>{{ ucfirst($lastMaint->maintenance_type) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('Km') }}</td>
                            <td>{{ number_format($lastMaint->kilometers_at_maintenance ?? 0, 0, '', ' ') }}</td>
                        </tr>
                    </table>
                    @else
                    <x-empty-state icon="la la-wrench" :message="__('Aucune maintenance enregistree')" />
                    @endif
                </div>
            </div>
        </div>
    </div>

</x-layouts.main>
