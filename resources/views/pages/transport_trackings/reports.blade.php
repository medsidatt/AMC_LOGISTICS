<x-layouts.main :title="__('Dashboard Analytique')">

    {{-- ═══════════ FILTERS ═══════════ --}}
    <div class="card shadow-sm mb-2">
        <div class="card-body py-1">
            <form method="get" class="row align-items-end">
                <div class="form-group col-6 col-lg-2">
                    <label>{{ __('Du') }}</label>
                    <input type="date" name="from" class="form-control"
                           value="{{ request('from', $from->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-6 col-lg-2">
                    <label>{{ __('Au') }}</label>
                    <input type="date" name="to" class="form-control"
                           value="{{ request('to', $to->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-6 col-lg-2">
                    <label>{{ __('Conducteur') }}</label>
                    <select name="driver_id" class="form-control select2" data-placeholder="{{ __('Tous') }}" data-allow-clear="true">
                        <option></option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" @selected(request('driver_id') == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-6 col-lg-2">
                    <label>{{ __('Camion') }}</label>
                    <select name="truck_id" class="form-control select2" data-placeholder="{{ __('Tous') }}" data-allow-clear="true">
                        <option></option>
                        @foreach($trucks as $t)
                            <option value="{{ $t->id }}" @selected(request('truck_id') == $t->id)>{{ $t->matricule }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-6 col-lg-2">
                    <label>{{ __('Fournisseur') }}</label>
                    <select name="provider_id" class="form-control select2" data-placeholder="{{ __('Tous') }}" data-allow-clear="true">
                        <option></option>
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}" @selected(request('provider_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-6 col-lg-2">
                    <div class="d-flex gap-1">
                        <button class="btn btn-primary btn-block"><i class="fas fa-filter"></i> {{ __('Filtrer') }}</button>
                        <a href="{{ route('dashboard.trackings') }}" class="btn btn-outline-secondary btn-block"><i class="fas fa-redo"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════ KPIs ═══════════ --}}
    <div class="row mb-2">
        {{-- Total Trips --}}
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-2"
                         style="width:48px;height:48px;background:rgba(115,103,240,0.12);">
                        <i class="fas fa-route" style="font-size:1.3rem;color:#7367f0;"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold">{{ number_format($totalTrips) }}</h4>
                        <small class="text-muted">{{ __('Rotations') }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Provider Tonnage --}}
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-2"
                         style="width:48px;height:48px;background:rgba(0,207,232,0.12);">
                        <i class="fas fa-weight-hanging" style="font-size:1.3rem;color:#00cfe8;"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold">{{ number_format($totalProviderWeight, 0, '.', ' ') }}</h4>
                        <small class="text-muted">{{ __('Poids Fournisseur (T)') }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Gap --}}
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-2"
                         style="width:48px;height:48px;background:{{ $totalGap < 0 ? 'rgba(234,84,85,0.12)' : 'rgba(40,199,111,0.12)' }};">
                        <i class="fas fa-balance-scale" style="font-size:1.3rem;color:{{ $totalGap < 0 ? '#ea5455' : '#28c76f' }};"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold {{ $totalGap < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($totalGap, 2, '.', ' ') }} T
                        </h4>
                        <small class="text-muted">{{ __('Écart total') }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Anomalies --}}
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-2"
                         style="width:48px;height:48px;background:rgba(234,84,85,0.12);">
                        <i class="fas fa-exclamation-triangle" style="font-size:1.3rem;color:#ea5455;"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold">{{ number_format($totalDiscrepanciesCount) }}</h4>
                        <small class="text-muted">{{ __('Anomalies') }} ({{ number_format($totalDiscrepancyKg, 0) }} T)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ SECONDARY KPIs ═══════════ --}}
    <div class="row mb-2">
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center py-1">
                    <small class="text-muted text-uppercase">{{ __('Ce mois') }}</small>
                    <h5 class="mb-0 font-weight-bold">{{ number_format($thisMonthTonnage, 0, '.', ' ') }} T</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center py-1">
                    <small class="text-muted text-uppercase">{{ __('Cette année') }}</small>
                    <h5 class="mb-0 font-weight-bold">{{ number_format($thisYearTonnage, 0, '.', ' ') }} T</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center py-1">
                    <small class="text-muted text-uppercase">{{ __('Conducteurs suspects') }}</small>
                    <h5 class="mb-0 font-weight-bold text-danger">{{ $suspiciousDrivers }}</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center py-1">
                    <small class="text-muted text-uppercase">{{ __('Poids Client') }}</small>
                    <h5 class="mb-0 font-weight-bold">{{ number_format($totalClientWeight, 0, '.', ' ') }} T</h5>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ CHARTS ROW 1 ═══════════ --}}
    <div class="row mb-2">
        {{-- Monthly Provider vs Client --}}
        <div class="col-lg-8 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-header py-1">
                    <h6 class="mb-0"><i class="fas fa-chart-area text-primary mr-1"></i> {{ __('Tonnage mensuel : Fournisseur vs Client') }}</h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyTonnageChart" height="100"></canvas>
                </div>
            </div>
        </div>

        {{-- Gap by Product (Doughnut) --}}
        <div class="col-lg-4 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-header py-1">
                    <h6 class="mb-0"><i class="fas fa-chart-pie text-info mr-1"></i> {{ __('Écart par produit') }}</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="gapByProductChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ CHARTS ROW 2 ═══════════ --}}
    <div class="row mb-2">
        {{-- Monthly Gap trend --}}
        <div class="col-lg-6 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-header py-1">
                    <h6 class="mb-0"><i class="fas fa-chart-line text-danger mr-1"></i> {{ __('Évolution des écarts') }}</h6>
                </div>
                <div class="card-body">
                    <canvas id="gapTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>

        {{-- Driver Risk (Horizontal bar) --}}
        <div class="col-lg-6 mb-1">
            <div class="card shadow-sm h-100">
                <div class="card-header py-1">
                    <h6 class="mb-0"><i class="fas fa-user-shield text-warning mr-1"></i> {{ __('Top 10 conducteurs par écart') }}</h6>
                </div>
                <div class="card-body">
                    <canvas id="driverRiskChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ GAP BY BASE ═══════════ --}}
    <div class="row mb-2">
        @foreach($gapByBase as $b)
            <div class="col-6 col-lg-{{ 12 / max($gapByBase->count(), 1) }} mb-1">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="text-uppercase text-muted mb-1">
                            {{ $b->base === 'mr' ? 'Mauritanie' : ($b->base === 'sn' ? 'Sénégal' : $b->base) }}
                        </h6>
                        <div class="d-flex justify-content-around">
                            <div>
                                <small class="text-muted">{{ __('Rotations') }}</small>
                                <h5 class="mb-0 font-weight-bold">{{ $b->trips }}</h5>
                            </div>
                            <div>
                                <small class="text-muted">{{ __('Fournisseur') }}</small>
                                <h5 class="mb-0">{{ number_format($b->prov, 0, '.', ' ') }}</h5>
                            </div>
                            <div>
                                <small class="text-muted">{{ __('Client') }}</small>
                                <h5 class="mb-0">{{ number_format($b->client, 0, '.', ' ') }}</h5>
                            </div>
                            <div>
                                <small class="text-muted">{{ __('Écart') }}</small>
                                <h5 class="mb-0 text-danger">{{ number_format($b->gap_sum, 0, '.', ' ') }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══════════ ANOMALIES TABLE ═══════════ --}}
    <div class="card shadow-sm mb-2">
        <div class="card-header py-1 d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="fas fa-exclamation-circle text-danger mr-1"></i> {{ __('Anomalies récentes') }} <span class="badge badge-danger">{{ $anomalies->count() }}</span></h6>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                <tr>
                    <th>{{ __('Réf') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Conducteur') }}</th>
                    <th>{{ __('Camion') }}</th>
                    <th class="text-right">{{ __('Fournisseur') }}</th>
                    <th class="text-right">{{ __('Client') }}</th>
                    <th class="text-right">{{ __('Écart') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($anomalies as $a)
                    @php $gap = $a->gap ?? 0; @endphp
                    <tr>
                        <td><strong>{{ $a->reference }}</strong></td>
                        <td>{{ $a->provider_date ? \Carbon\Carbon::parse($a->provider_date)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $a->driver?->name ?? '—' }}</td>
                        <td>{{ $a->truck?->matricule ?? '—' }}</td>
                        <td class="text-right">{{ number_format($a->provider_net_weight, 2) }}</td>
                        <td class="text-right">{{ number_format($a->client_net_weight, 2) }}</td>
                        <td class="text-right">
                            <span class="badge {{ $gap < 0 ? 'badge-danger' : ($gap == 0 ? 'badge-success' : 'badge-warning') }}">
                                {{ number_format($gap, 2) }} T
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="showModal({
                                        title: '{{ $a->reference }}',
                                        route: '{{ route('transport_tracking.show', $a->id) }}',
                                        size: 'lg'
                                    })">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-2">{{ __('Aucune anomalie') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════ TRIPS TABLE ═══════════ --}}
    <div class="card shadow-sm mb-2">
        <div class="card-header py-1">
            <h6 class="mb-0"><i class="fas fa-list text-primary mr-1"></i> {{ __('Toutes les rotations') }}</h6>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead class="thead-light">
                <tr>
                    <th>{{ __('Réf') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th class="text-right">{{ __('Fournisseur') }}</th>
                    <th class="text-right">{{ __('Client') }}</th>
                    <th class="text-right">{{ __('Écart') }}</th>
                    <th>{{ __('Conducteur') }}</th>
                    <th>{{ __('Camion') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($trips as $row)
                    @php $g = $row->gap ?? 0; @endphp
                    <tr>
                        <td><strong>{{ $row->reference }}</strong></td>
                        <td>{{ $row->provider_date ? \Carbon\Carbon::parse($row->provider_date)->format('d/m/Y') : '—' }}</td>
                        <td class="text-right">{{ number_format($row->provider_net_weight, 2) }}</td>
                        <td class="text-right">{{ number_format($row->client_net_weight, 2) }}</td>
                        <td class="text-right">
                            <span class="badge {{ $g < 0 ? 'badge-danger' : ($g == 0 ? 'badge-success' : 'badge-warning') }}">
                                {{ number_format($g, 2) }}
                            </span>
                        </td>
                        <td>{{ $row->driver?->name ?? '—' }}</td>
                        <td>{{ $row->truck?->matricule ?? '—' }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="showModal({
                                        title: '{{ $row->reference }}',
                                        route: '{{ route('transport_tracking.show', $row->id) }}',
                                        size: 'lg'
                                    })">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($trips->hasPages())
            <div class="card-footer d-flex justify-content-center py-1">
                {{ $trips->links() }}
            </div>
        @endif
    </div>

    @push('scripts')
        <script src="{{ asset('app-assets/vendors/js/charts/chart.min.js') }}"></script>
        <script>
            const COLORS = {
                primary: 'rgba(115,103,240,0.8)',
                primaryLight: 'rgba(115,103,240,0.15)',
                info: 'rgba(0,207,232,0.8)',
                infoLight: 'rgba(0,207,232,0.15)',
                danger: 'rgba(234,84,85,0.8)',
                dangerLight: 'rgba(234,84,85,0.15)',
                warning: 'rgba(255,159,67,0.8)',
                success: 'rgba(40,199,111,0.8)',
            };

            const chartDefaults = {
                responsive: true,
                maintainAspectRatio: true,
                legend: { labels: { fontFamily: "'Open Sans', sans-serif", fontSize: 12 } },
                tooltips: { mode: 'index', intersect: false },
            };

            // ── Monthly Tonnage: Provider vs Client ──
            new Chart(document.getElementById('monthlyTonnageChart'), {
                type: 'bar',
                data: {
                    labels: {!! json_encode($months) !!},
                    datasets: [
                        {
                            label: '{{ __("Fournisseur") }}',
                            data: {!! json_encode($monthlyProvider) !!},
                            backgroundColor: COLORS.primary,
                            borderRadius: 4,
                        },
                        {
                            label: '{{ __("Client") }}',
                            data: {!! json_encode($monthlyClient) !!},
                            backgroundColor: COLORS.info,
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    ...chartDefaults,
                    scales: {
                        xAxes: [{ gridLines: { display: false } }],
                        yAxes: [{ ticks: { beginAtZero: true, callback: v => v.toLocaleString() + ' T' } }]
                    }
                }
            });

            // ── Gap by Product (Doughnut) ──
            new Chart(document.getElementById('gapByProductChart'), {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($gapByProduct->pluck('product')) !!},
                    datasets: [{
                        data: {!! json_encode($gapByProduct->pluck('gap_sum')->map(fn($v) => round($v, 2))) !!},
                        backgroundColor: [COLORS.primary, COLORS.info, COLORS.warning, COLORS.danger, COLORS.success],
                    }]
                },
                options: {
                    ...chartDefaults,
                    cutoutPercentage: 65,
                    legend: { position: 'bottom', labels: { padding: 15, fontFamily: "'Open Sans', sans-serif" } }
                }
            });

            // ── Gap Trend (Line) ──
            new Chart(document.getElementById('gapTrendChart'), {
                type: 'line',
                data: {
                    labels: {!! json_encode($months) !!},
                    datasets: [{
                        label: '{{ __("Écart total") }}',
                        data: {!! json_encode($monthlyGap) !!},
                        borderColor: COLORS.danger,
                        backgroundColor: COLORS.dangerLight,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: COLORS.danger,
                    }]
                },
                options: {
                    ...chartDefaults,
                    scales: {
                        xAxes: [{ gridLines: { display: false } }],
                        yAxes: [{ ticks: { beginAtZero: true, callback: v => v.toLocaleString() + ' T' } }]
                    }
                }
            });

            // ── Driver Risk (Horizontal Bar) ──
            const driverNames = {!! json_encode($driverRisk->map(fn($r) => $r->driver?->name ?? 'ID '.$r->driver_id)) !!};
            const driverGaps  = {!! json_encode($driverRisk->pluck('sum_gap')->map(fn($v) => round($v, 2))) !!};
            new Chart(document.getElementById('driverRiskChart'), {
                type: 'horizontalBar',
                data: {
                    labels: driverNames,
                    datasets: [{
                        label: '{{ __("Écart cumulé (T)") }}',
                        data: driverGaps,
                        backgroundColor: driverGaps.map((v, i) => i < 3 ? COLORS.danger : COLORS.warning),
                        borderRadius: 4,
                    }]
                },
                options: {
                    ...chartDefaults,
                    legend: { display: false },
                    scales: {
                        xAxes: [{ ticks: { beginAtZero: true, callback: v => v.toLocaleString() + ' T' } }],
                        yAxes: [{ gridLines: { display: false } }]
                    }
                }
            });
        </script>
    @endpush

</x-layouts.main>
