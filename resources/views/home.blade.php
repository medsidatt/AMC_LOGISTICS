<x-layouts.main
    :title="__('Dashboard')"
>
    <style>
        .icon-big {
            font-size: 3rem !important;
            line-height: 1;
        }
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>

    {{-- Row 1: KPI Cards --}}
    <div class="row">
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-truck text-primary mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __('Camions') }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $trucksCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-id-card text-success mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __('Conducteurs') }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $driversCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-route text-info mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __("Voyages aujourd'hui") }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $tripsToday }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center py-2 px-3">
                    <i class="la la-weight text-warning mr-3 icon-big"></i>
                    <div>
                        <div class="small text-muted">{{ __('Tonnage du mois') }}</div>
                        <div class="h5 mb-0 font-weight-bold">{{ number_format($tonnageMonth, 0, '', ' ') }} kg</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($unresolvedAlerts > 0)
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-center mb-0">
                <i class="la la-exclamation-triangle mr-2" style="font-size: 1.5rem;"></i>
                <strong>{{ $unresolvedAlerts }} {{ __('alertes non resolues') }}</strong>
                <a href="{{ route('logistics.dashboard') }}" class="btn btn-sm btn-outline-warning ml-auto">{{ __('Voir') }}</a>
            </div>
        </div>
    </div>
    @endif

    {{-- Row 2: Monthly Tonnage Chart --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="la la-chart-bar mr-2"></i>
                    <strong>{{ __('Tonnage mensuel (6 derniers mois)') }}</strong>
                </div>
                <div class="card-body">
                    <div id="tonnage-chart"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: Maintenance Due + Recent Trackings --}}
    <div class="row">
        {{-- Left: Trucks due for maintenance --}}
        <div class="col-12 col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="la la-wrench mr-2 text-danger"></i>
                    <strong>{{ __('Maintenance requise') }}</strong>
                </div>
                <div class="card-body p-0">
                    @forelse($trucksDueMaintenance as $truck)
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div>
                            <strong>{{ $truck->matricule }}</strong>
                            <div class="small text-muted">{{ $truck->transporter?->name ?? '-' }}</div>
                        </div>
                        <span class="badge badge-danger">{{ __('A faire') }}</span>
                    </div>
                    @empty
                    <div class="p-3 text-center text-muted">
                        <i class="la la-check-circle" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-1">{{ __('Aucune maintenance requise') }}</p>
                    </div>
                    @endforelse
                </div>
                @if($trucksDueMaintenance->count() > 0)
                <div class="card-footer text-center">
                    <a href="{{ route('logistics.dashboard') }}" class="text-primary">{{ __('Voir tout') }} &rarr;</a>
                </div>
                @endif
            </div>
        </div>

        {{-- Right: Recent Transport Trackings --}}
        <div class="col-12 col-lg-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="la la-list mr-2"></i>
                    <strong>{{ __('Derniers suivis de transport') }}</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Camion') }}</th>
                                <th>{{ __('Conducteur') }}</th>
                                <th>{{ __('Poids Net (F)') }}</th>
                                <th>{{ __('Ecart') }}</th>
                                <th>{{ __('Date') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recentTrackings as $tracking)
                                <tr>
                                    <td>{{ $tracking->reference }}</td>
                                    <td>{{ $tracking->truck?->matricule ?? '-' }}</td>
                                    <td>{{ $tracking->driver?->name ?? '-' }}</td>
                                    <td>{{ number_format($tracking->provider_net_weight ?? 0, 0, '', ' ') }}</td>
                                    <td>
                                        @php $gap = $tracking->gap ?? 0; @endphp
                                        <span class="badge badge-{{ abs($gap) > 150 ? 'danger' : (abs($gap) > 50 ? 'warning' : 'success') }}">
                                            {{ number_format($gap, 0, '', ' ') }}
                                        </span>
                                    </td>
                                    <td>{{ $tracking->provider_date }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">{{ __('Aucun suivi') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 4: Quick Actions --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('transport_tracking.create-page') }}" class="btn btn-primary quick-action-btn">
                    <i class="la la-plus"></i> {{ __('Nouveau suivi') }}
                </a>
                <a href="{{ route('trucks.create-page') }}" class="btn btn-outline-primary quick-action-btn">
                    <i class="la la-truck"></i> {{ __('Nouveau camion') }}
                </a>
                <a href="{{ route('transport_tracking.index') }}" class="btn btn-outline-info quick-action-btn">
                    <i class="la la-list"></i> {{ __('Tous les suivis') }}
                </a>
            </div>
        </div>
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Monthly Tonnage Chart (ApexCharts)
    var monthlyData = @json($monthlyTonnage);

    if (typeof ApexCharts !== 'undefined' && monthlyData.length > 0) {
        var options = {
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false }
            },
            series: [
                {
                    name: '{{ __("Tonnage (kg)") }}',
                    data: monthlyData.map(function(item) { return Math.round(item.total_weight); })
                },
                {
                    name: '{{ __("Voyages") }}',
                    data: monthlyData.map(function(item) { return item.trip_count; })
                }
            ],
            xaxis: {
                categories: monthlyData.map(function(item) { return item.month; })
            },
            yaxis: [
                {
                    title: { text: '{{ __("Tonnage (kg)") }}' },
                    labels: { formatter: function(val) { return val.toLocaleString(); } }
                },
                {
                    opposite: true,
                    title: { text: '{{ __("Voyages") }}' }
                }
            ],
            colors: ['#666ee8', '#28d094'],
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '50%' }
            },
            dataLabels: { enabled: false }
        };

        new ApexCharts(document.querySelector('#tonnage-chart'), options).render();
    } else if (document.querySelector('#tonnage-chart')) {
        document.querySelector('#tonnage-chart').innerHTML =
            '<p class="text-muted text-center py-4">{{ __("Pas de donnees pour cette periode") }}</p>';
    }
});
</script>
@endpush

</x-layouts.main>
