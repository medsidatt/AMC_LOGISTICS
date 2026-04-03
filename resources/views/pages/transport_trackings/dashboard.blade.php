<x-layouts.main :title="__('Dashboard')" :breadcrumbs="$breadcrumbs">

    <!-- Filters -->
    <div class="row mb-2">
        <div class="col-12">
            <form id="dashboard-filters">
                <div class="row mb-1">
                    <div class="col-6">
                        <label>{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-6">
                        <label>{{ __('End Date') }}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-md-4">
                        <label>{{ __('Transporter') }}</label>
                        <select name="transporter_id" class="form-control">
                            <option value="">{{ __('All') }}</option>
                            @foreach($transporters as $t)
                                <option value="{{ $t->id }}" {{ request('transporter_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('Truck') }}</label>
                        <select name="truck_id" class="form-control">
                            <option value="">{{ __('All') }}</option>
                            @foreach($trucks as $t)
                                <option value="{{ $t->id }}" {{ request('truck_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->matricule }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('Driver') }}</label>
                        <select name="driver_id" class="form-control">
                            <option value="">{{ __('All') }}</option>
                            @foreach($drivers as $d)
                                <option value="{{ $d->id }}" {{ request('driver_id') == $d->id ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label>{{ __('Provider') }}</label>
                        <select name="provider_id" class="form-control">
                            <option value="">{{ __('All') }}</option>
                            @foreach($providers as $p)
                                <option value="{{ $p->id }}" {{ request('provider_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Section -->
    <div class="row match-height">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <div id="kpi-container">
                        @include("pages.transport_trackings.partials.kpis")
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Calendar -->
    <div class="row match-height mt-2">
        <!-- Monthly Weights Chart -->
        <div class="col-xl-4 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h6>{{ __('Poids Transporté par Mois') }} ({{ $year }})</h6>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div id="analysis-graph" class="height-250"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="col-xl-8 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h6>{{ __('Calendrier des Transports') }}</h6>
                </div>
                <div class="card-body">
                    <div id="dashboard-calendar"></div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <!-- ApexCharts -->
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            var analysisOptions = {
                chart: { type: 'bar', height: 250 },
                series: [{
                    name: 'Poids Transporté',
                    data: {!! json_encode($monthlyWeights) !!}
                }],
                xaxis: {
                    categories: {!! json_encode($months) !!}
                }
            };
            // var analysisChart = new ApexCharts(document.querySelector("#analysis-graph"), analysisOptions);
            // analysisChart.render();
        </script>

        <!-- FullCalendar -->
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('dashboard-calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    events: {!! json_encode($events) !!}
                });
                calendar.render();
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('dashboard-filters');
                let analysisChart; // store chart instance globally

                function renderChart(weights) {
                    const options = {
                        chart: { type: 'bar', height: 250 },
                        series: [{
                            name: 'Poids Transporté',
                            data: weights
                        }],
                        xaxis: {
                            categories: {!! json_encode($months) !!}
                        }
                    };

                    if (analysisChart) {
                        analysisChart.updateOptions({ series: [{ data: weights }] });
                    } else {
                        analysisChart = new ApexCharts(document.querySelector("#analysis-graph"), options);
                        analysisChart.render();
                    }
                }

                // Initial chart render
                renderChart({!! json_encode($monthlyWeights) !!});

                form.addEventListener('change', function() {
                    let formData = new FormData(form);
                    let params = new URLSearchParams(formData).toString();

                    fetch("{{ route('transport_tracking.dashboard') }}?" + params, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(response => response.json())
                        .then(data => {
                            // Update KPIs
                            document.getElementById('kpi-container').innerHTML = data.kpis;

                            // Update chart
                            renderChart(data.monthlyWeights);
                        })
                        .catch(err => console.error(err));
                });
            });
        </script>


    @endpush
</x-layouts.main>
