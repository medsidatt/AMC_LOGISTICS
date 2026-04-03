<x-layouts.main
    title="{{ __('Camions') }}"
    :actions="$actions"
>
    @if($maintenanceDueTrucks->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-tools mr-2"></i> Camions Nécessitant Maintenance
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light">{{ $maintenanceDueTrucks->count() }}</span>
                    <button type="button" class="btn btn-sm btn-light" onclick="applyBulkMaintenance()">
                        <i class="fas fa-check-double"></i> Appliquer Maintenance (Sélection)
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                {{-- Bulk Actions Bar --}}
                <div class="bg-light p-2 border-bottom d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllTrucks" onchange="toggleSelectAll(this)">
                        <label class="form-check-label" for="selectAllTrucks">
                            <strong>Tout sélectionner</strong>
                        </label>
                    </div>
                    <div>
                        <span id="selectedCount" class="text-muted">0 sélectionné(s)</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush">
                    @foreach($maintenanceDueTrucks as $truck)
                        @if($truck->is_active)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="form-check me-3">
                                    <input class="form-check-input truck-checkbox"
                                           type="checkbox"
                                           value="{{ $truck->id }}"
                                           id="truck-{{ $truck->id }}"
                                           onchange="updateSelectedCount()">
                                </div>
                                <div>
                                    <a href="javascript:void(0)"
                                       onclick="showModal({
                                           title: 'Détails Camion - {{ $truck->matricule }}',
                                           route: '{{ route('trucks.show', $truck->id) }}',
                                           size: 'xl'
                                       })"
                                       class="fw-bold text-primary">
                                        {{ $truck->matricule }}
                                    </a>
                                    <small class="d-block text-muted">
                                        Dernière Maintenance: {{ $truck->last_maintenance_date ? \Carbon\Carbon::parse($truck->last_maintenance_date)->format('d/m/Y') : 'Aucune' }}
                                        <span class="badge bg-danger ms-2">
                                            @if($truck->usesKilometerMaintenance())
                                                {{ number_format($truck->maintenanceCounterByType(), 0) }} {{ $truck->maintenanceUnitByType() }}
                                            @else
                                                {{ $truck->maintenanceCounterByType() }} {{ $truck->maintenanceUnitByType() }}
                                            @endif
                                        </span>
                                    </small>
                                </div>
                            </div>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    onclick="showModal({
                                                 title: 'Appliquer Maintenance - {{ $truck->matricule }}',
                                                 route: '{{ route('trucks.maintenances.create', $truck->id) }}',
                                                 size: 'md'})"
                            >
                                <i class="fas fa-wrench"></i> Appliquer
                            </button>
                        </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
    @else
        <div class="alert alert-success shadow-sm">
            <i class="fas fa-check-circle mr-2"></i>
            Tous les camions sont à jour avec la maintenance.
        </div>
    @endif


    <div class="card">
        <div class="card-body table-responsive">
            <table
                class="table table-striped table-bordered w-100 dt-responsive nowrap"
                data-url="{{ route('trucks.index') }}"
                data-column='id,matricule,is_active,maintenance_type,current_counter,last_maintenance_counter,next_maintenance_counter,maintenance_due,total_rotations,transporter_id,actions'
                data-order='[]'
                data-priorities="10,1,3,4,5,7,8,2,6,9,2"
            >
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Matricule') }}</th>
                    <th>{{ __('Statut') }}</th>
                    <th>{{ 'Type Maintenance' }}</th>
                    <th>{{ 'Compteur Actuel' }}</th>
                    <th>{{ 'Compteur Dernière Maintenance' }}</th>
                    <th>{{ 'Compteur Prochaine Maintenance' }}</th>
                    <th>{{ 'Maintenance due' }}</th>
                    <th>{{ 'Total Rotations' }}</th>
                    <th>{{ __('Transporteur') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
        function updateMaintenanceType(truckId, maintenanceType) {
            Swal.showLoading();
            $.ajax({
                url: `{{ url('/trucks') }}/${truckId}/update-maintenance-type`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    maintenance_type: maintenanceType
                },
                success: function (response) {
                    Swal.fire({
                        type: 'success',
                        title: response.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    }).then(() => {
                        // Full reload required to refresh server-rendered sections
                        // like "Camions Nécessitant Maintenance"
                        location.reload();
                    });
                },
                error: function (xhr) {
                    Swal.fire({
                        type: 'error',
                        title: 'Erreur lors de la mise à jour',
                    });
                }
            });
        }

        function bulkUpdateMaintenanceType(event) {
            if (event) {
                event.preventDefault();
            }

            Swal.fire({
                title: 'Changer le type de maintenance pour tous les camions',
                text: 'Veuillez choisir le nouveau type de maintenance pour TOUS les camions.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Mettre à jour',
                cancelButtonText: 'Annuler',
                input: 'radio',
                inputOptions: {
                    'rotations': 'Rotations',
                    'kilometers': 'Kilomètres'
                },
                inputValidator: (value) => {
                    if (!value) {
                        return 'Vous devez choisir un type de maintenance !';
                    }
                }
            }).then((result) => {
                const isConfirmed = (typeof result.isConfirmed !== 'undefined')
                    ? result.isConfirmed
                    : !!result.value;

                if (isConfirmed) {
                    const newMaintenanceType = result.value;
                    Swal.showLoading();
                    $.ajax({
                        url: '{{ route("trucks.bulk-update-maintenance-type") }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            maintenance_type: newMaintenanceType
                        },
                        success: function (response) {
                            Swal.fire({
                                type: 'success',
                                title: response.message,
                            }).then(() => {
                                // Full reload required to refresh server-rendered sections
                                // like "Camions Nécessitant Maintenance"
                                location.reload();
                            });
                        },
                        error: function (xhr) {
                            let message = 'Erreur lors de la mise à jour globale du type de maintenance.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                type: 'error',
                                title: 'Erreur',
                                text: message
                            });
                        }
                    });
                }
            });
        }

        function bulkUpdateKmInterval(event) {
            if (event) {
                event.preventDefault();
            }

            Swal.fire({
                title: 'Changer l\'intervalle KM pour tous les camions',
                text: 'Définissez le nombre de kilomètres avant maintenance.',
                icon: 'question',
                input: 'number',
                inputValue: '{{ \App\Models\Truck::MAX_KM_BEFORE_MAINTENANCE }}',
                inputAttributes: {
                    min: 1,
                    step: 1
                },
                showCancelButton: true,
                confirmButtonText: 'Mettre à jour',
                cancelButtonText: 'Annuler',
                inputValidator: (value) => {
                    const number = Number(value);
                    if (!value || Number.isNaN(number) || number <= 0) {
                        return 'Veuillez saisir un intervalle valide (> 0).';
                    }
                }
            }).then((result) => {
                const isConfirmed = (typeof result.isConfirmed !== 'undefined')
                    ? result.isConfirmed
                    : !!result.value;

                if (!isConfirmed) {
                    return;
                }

                Swal.showLoading();
                $.ajax({
                    url: '{{ route("trucks.bulk-update-km-interval") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        km_maintenance_interval: Number(result.value)
                    },
                    success: function (response) {
                        Swal.fire({
                            type: 'success',
                            title: response.message,
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function (xhr) {
                        let message = 'Erreur lors de la mise à jour de l\'intervalle KM.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            type: 'error',
                            title: 'Erreur',
                            text: message
                        });
                    }
                });
            });
        }

        function toggleTruckStatus(truckId) {
            Swal.fire({
                title: 'Confirmer!',
                text: 'Voulez-vous vraiment changer le statut de ce camion?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, changer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.value) {
                    Swal.showLoading();
                    $.ajax({
                        url: `{{ url('/trucks') }}/${truckId}/toggle-active`,
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function (response) {
                            Swal.fire({
                                type: 'success',
                                title: response.message,
                            });

                            // Reload datatable
                            $('table[data-column]').each(function () {
                                let table = $(this);
                                let dataTable = table.DataTable();
                                dataTable.ajax.reload();
                            });
                        },
                        error: function (xhr) {
                            Swal.fire({
                                type: 'error',
                                title: 'Erreur lors du changement de statut',
                            });
                        }
                    });
                }
            });
        }

        // Toggle select all checkboxes
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.truck-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }

        // Update selected count display
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.truck-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected + ' sélectionné(s)';

            // Update select all checkbox state
            const total = document.querySelectorAll('.truck-checkbox').length;
            const selectAll = document.getElementById('selectAllTrucks');
            if (selectAll) {
                selectAll.checked = selected === total && total > 0;
                selectAll.indeterminate = selected > 0 && selected < total;
            }
        }

        // Apply bulk maintenance
        function applyBulkMaintenance() {
            const selectedTrucks = [];
            document.querySelectorAll('.truck-checkbox:checked').forEach(cb => {
                selectedTrucks.push(cb.value);
            });

            if (selectedTrucks.length === 0) {
                Swal.fire({
                    type: 'warning',
                    title: 'Aucun camion sélectionné',
                    text: 'Veuillez sélectionner au moins un camion.'
                });
                return;
            }

            Swal.fire({
                title: 'Appliquer Maintenance',
                html: `
                    <p>Vous allez appliquer la maintenance à <strong>${selectedTrucks.length}</strong> camion(s).</p>
                    <div class="form-group text-left mt-3">
                        <label for="bulk-maintenance-date"><strong>Date de maintenance:</strong></label>
                        <input type="date" id="bulk-maintenance-date" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="form-group text-left mt-3">
                        <label for="bulk-maintenance-notes"><strong>Notes (optionnel):</strong></label>
                        <textarea id="bulk-maintenance-notes" class="form-control" rows="2" placeholder="Notes..."></textarea>
                    </div>
                `,
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Appliquer Maintenance',
                cancelButtonText: 'Annuler',
                preConfirm: () => {
                    const date = document.getElementById('bulk-maintenance-date').value;
                    if (!date) {
                        Swal.showValidationMessage('La date est requise');
                        return false;
                    }
                    return {
                        date: date,
                        notes: document.getElementById('bulk-maintenance-notes').value
                    };
                }
            }).then((result) => {
                if (result.value) {
                    Swal.showLoading();
                    $.ajax({
                        url: '{{ route("trucks.maintenances.bulk-store") }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            truck_ids: selectedTrucks,
                            date: result.value.date,
                            notes: result.value.notes
                        },
                        success: function (response) {
                            Swal.fire({
                                type: 'success',
                                title: response.message,
                            }).then(() => {
                                location.reload();
                            });
                        },
                        error: function (xhr) {
                            let message = 'Erreur lors de l\'application de la maintenance';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                type: 'error',
                                title: 'Erreur',
                                text: message
                            });
                        }
                    });
                }
            });
        }
    </script>
    @endpush
</x-layouts.main>
