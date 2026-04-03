<x-layouts.main :title="__('Dashboard')">
    <div class="container py-4">

        <h1 class="mb-3">Transport Tracking Dashboard</h1>

        {{-- Filters --}}
        <form method="get" class="row g-2 mb-3">
            <div class="col-auto">
                <input type="date" name="from" class="form-control"
                       value="{{ request('from', now()->subMonths(3)->format('Y-m-d')) }}">
            </div>
            <div class="col-auto">
                <input type="date" name="to" class="form-control" value="{{ request('to', now()->format('Y-m-d')) }}">
            </div>
            <div class="col-auto">
                <select name="driver_id" class="form-select">
                    <option value="">-- Driver --</option>
                    @foreach($drivers as $d)
                        <option value="{{ $d->id }}" @selected(request('driver_id') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="truck_id" class="form-select">
                    <option value="">-- Truck --</option>
                    @foreach($trucks as $t)
                        <option
                            value="{{ $t->id }}" @selected(request('truck_id') == $t->id)>{{ $t->matricule }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <input type="text" name="product" class="form-control" placeholder="Product"
                       value="{{ request('product') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('dashboard.trackings') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        {{-- KPIs --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card p-3">
                    <h6>Suspicious Drivers</h6>
                    <h3>{{ number_format($suspiciousDrivers) }}</h3>
                    <small class="text-muted">Drivers with gap &gt; threshold</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <h6>Total Anomalous Trips</h6>
                    <h3>{{ number_format($totalDiscrepanciesCount) }}</h3>
                    <small class="text-muted">{{ number_format($totalDiscrepancyKg) }} kg total discrepancy</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <h6>This Month (kg)</h6>
                    <h3>{{ number_format($thisMonthTonnage, 2) }}</h3>
                    <small class="text-muted">Provider net weight</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <h6>Total</h6>
                    <h3>{{ number_format($totalTonnage, 2) }}</h3>
                    <small class="text-muted">All time provider net</small>
                </div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="card p-3">
                    <h5>Weight Discrepancy Over Time</h5>
                    <canvas id="discrepancyChart" height="120"></canvas>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-3">
                    <h5>Driver Risk Scores</h5>
                    <canvas id="driverRiskChart" height="200"></canvas>
                </div>
            </div>
        </div>

        {{-- Monthly tonnage --}}
        <div class="card p-3 mb-4">
            <h5>Monthly Tonnage</h5>
            <canvas id="monthlyTonnageChart" height="80"></canvas>
        </div>

        {{-- Anomalies table --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Recent Anomalies (gap &gt; threshold)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Provider Date</th>
                            <th>Driver</th>
                            <th>Truck</th>
                            <th>Prov Net (kg)</th>
                            <th>Client Net (kg)</th>
                            <th>Gap (kg)</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($anomalies as $a)
                            @php $gap = ($a->provider_net_weight ?? 0) - ($a->client_net_weight ?? 0); @endphp
                            <tr class="@if(abs($gap) > 500) anomaly-row @endif">
                                <td>{{ $a->reference }}</td>
                                <td>{{ optional($a->provider_date)->format('d/m/Y') }}</td>
                                <td>{{ optional($a->driver)->name }}</td>
                                <td>{{ optional($a->truck)->matricule }}</td>
                                <td>{{ number_format($a->provider_net_weight, 2) }}</td>
                                <td>{{ number_format($a->client_net_weight, 2) }}</td>
                                <td>
                                    <span
                                        class="badge {{ $gap == 0 ? 'bg-success' : ( $gap > 0 ? 'bg-danger' : 'bg-warning') }}">
                                        {{ number_format($gap, 2) }} kg
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="showModal({
                                            title: 'Tracking {{ $a->reference }}',
                                            route: '{{ route('transport_tracking.show', $a->id) }}',
                                            size: 'lg'
                                        })">Details
                                    </button>

                                    @if($a->provider_file && Storage::disk('public')->exists($a->provider_file))
                                        <button class="btn btn-sm btn-primary"
                                                onclick="showModal({
                                                title: 'Provider file',
                                                route: '{{ asset('storage/'.$a->provider_file) }}',
                                                size: 'xl',
                                                file: true
                                            })">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5>Latest Timeline</h5>
                <ul class="timeline">
                    @foreach($timeline->take(30) as $t)
                        <li>
                            <strong>{{ $t->reference }}</strong> —
                            {{ optional($t->provider_date)->format('d M Y') }} |
                            <span
                                class="text-muted">{{ optional($t->driver)->name }} / {{ optional($t->truck)->matricule }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Trips paginated --}}
        <div class="card mb-5">
            <div class="card-body">
                <h5>Trips</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Prov Date</th>
                            <th>Prov Net</th>
                            <th>Client Net</th>
                            <th>Gap</th>
                            <th>Driver</th>
                            <th>Truck</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($trips as $row)
                            @php $g = ($row->provider_net_weight ?? 0) - ($row->client_net_weight ?? 0); @endphp
                            <tr>
                                <td>{{ $row->reference }}</td>
                                <td>{{ optional($row->provider_date)->format('d/m/Y') }}</td>
                                <td>{{ number_format($row->provider_net_weight, 2) }}</td>
                                <td>{{ number_format($row->client_net_weight, 2) }}</td>
                                <td>
                                    <span
                                        class="badge {{ $g == 0 ? 'bg-success' : ($g > 0 ? 'bg-danger' : 'bg-warning') }}">
                                        {{ number_format($g, 2) }}
                                    </span>
                                </td>
                                <td>{{ optional($row->driver)->name }}</td>
                                <td>{{ optional($row->truck)->matricule }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="showModal({
                                            title: 'Tracking {{ $row->reference }}',
                                            route: '{{ route('transport_tracking.show', $row->id) }}',
                                            size: 'lg'
                                        })">
                                        View
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $trips->links() }}
                </div>
            </div>
        </div>

    </div>


    @push('scripts')

        {{-- Dependencies --}}

        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        {{-- Chart data --}}
        <script>
            // Discrepancy chart: we will plot sum abs gap per day (quick approach)
            const months = {!! json_encode($months) !!};
            const monthlyTonnage = {!! json_encode($monthlyTonnage) !!};

            // monthly tonnage
            new Chart(document.getElementById('monthlyTonnageChart'), {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Tonnage (converted)',
                        data: monthlyTonnage,
                        backgroundColor: 'rgba(13,110,253,0.6)'
                    }]
                },
                options: {responsive: true}
            });

            // driver risk: top 10
            const driverLabels = {!! json_encode($driverRisk->pluck('driver_id')->map(function($id){ return 'Driver ' . $id; })->take(10)) !!};
            const driverScores = {!! json_encode($driverRisk->pluck('risk_score')->map(fn($v)=> round($v,2))->take(10)) !!};
            new Chart(document.getElementById('driverRiskChart'), {
                type: 'bar',
                data: {
                    labels: driverLabels,
                    datasets: [{label: 'Risk score', data: driverScores}]
                },
                options: {indexAxis: 'y', responsive: true}
            });

            // discrepancy chart: daily sum abs gap (we build from server side optionally)
            // For demo, we will reuse monthlyTonnage as x; ideally you compute daily gaps in controller and pass them
            new Chart(document.getElementById('discrepancyChart'), {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Monthly Tonnage (proxy)',
                        data: monthlyTonnage,
                        fill: false,
                        tension: .3
                    }]
                },
                options: {responsive: true}
            });

            // showModal must exist in your app; if not, fallback to simple window.open
            function showModal(opts) {
                if (window.showModal) return window.showModal(opts);
                // fallback
                window.open(opts.route, '_blank');
            }
        </script>

    @endpush
</x-layouts.main>
