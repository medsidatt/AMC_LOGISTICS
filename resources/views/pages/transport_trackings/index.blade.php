<x-layouts.main
    :title="__('Suivi stock')"
    :actions="$actions">
    <div class="card">
        <div class="card-header">
            <div class="row">
                {{-- 🔹 Date Range (Delivery Date) --}}
                <x-forms.input
                    data-filter="start_date"
                    class="col-6"
                    name="start_date"
                    label="{{ __('Du') }}"
                    type="date"
                    :value="$filters['start_date'] ?? ''"
                    placeholder="{{ __('Date de début') }}"
                />
                <x-forms.input
                    data-filter="end_date"
                    class="col-6"
                    name="end_date"
                    label="{{ __('Au') }}"
                    type="date"
                    :value="$filters['end_date'] ?? ''"
                    placeholder="{{ __('Date de fin') }}"
                />

                {{-- 🔹 Select Filters --}}
                <x-forms.select
                    data-filter="provider_id_filter"
                    class="col-6 col-lg-3"
                    name="provider_id_filter"
                    label="{{ __('Fournisseurs') }}"
                    :options="$providers"
                    placeholder="{{ __('Sélectionner un fournisseur') }}"
                    :selected="$filters['provider_id_filter'] ?? ''"
                />
                <x-forms.select
                    data-filter="transporter_id_filter"
                    class="col-6 col-lg-3"
                    name="transporter_id_filter"
                    label="{{ __('Transporteurs') }}"
                    :options="$transporters"
                    placeholder="{{ __('Sélectionner un transporteur') }}"
                    :selected="$filters['transporter_id_filter'] ?? ''"
                />
                <x-forms.select
                    data-filter="truck_id_filter"
                    class="col-6 col-lg-3"
                    name="truck_id_filter"
                    label="{{ __('Camions') }}"
                    :options="$trucks"
                    placeholder="{{ __('Sélectionner un camion') }}"
                    :label-field="'matricule'"
                    :selected="$filters['truck_id_filter'] ?? ''"
                />
                <x-forms.select
                    data-filter="driver_id_filter"
                    class="col-6 col-lg-3"
                    name="driver_id_filter"
                    label="{{ __('Conducteurs') }}"
                    :options="$drivers"
                    placeholder="{{ __('Sélectionner un conducteur') }}"
                    :selected="$filters['driver_id_filter'] ?? ''"
                />
            </div>
        </div>

        <div class="card-body">
            <table
                class="table table-striped table-bordered w-100"
                data-url="{{ route('transport_tracking.index') }}"
                data-column="reference,client_date,provider_net_weight,client_net_weight,gap,actions"
                data-default-order="0,desc"
            >
                <thead>
                <tr>
                    <th>{{ __('Référence') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Poids Fournisseur') }}</th>
                    <th>{{ __('Poids Client') }}</th>
                    <th>{{ __('Écart') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                {{-- Data will be populated here by DataTables --}}
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            function exportFiltered(event) {
                event.preventDefault();

                const params = new URLSearchParams({
                    start_date: document.querySelector('[name="start_date"]').value,
                    end_date: document.querySelector('[name="end_date"]').value,
                    provider_id_filter: document.querySelector('[name="provider_id_filter"]').value,
                    transporter_id_filter: document.querySelector('[name="transporter_id_filter"]').value,
                    truck_id_filter: document.querySelector('[name="truck_id_filter"]').value,
                    driver_id_filter: document.querySelector('[name="driver_id_filter"]').value,
                });

                window.location.href = "{{ route('transport_tracking.export') }}?" + params.toString();
            }

        </script>
    @endpush
</x-layouts.main>
