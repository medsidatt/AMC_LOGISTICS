<x-layouts.main title="{{ __('Logistics Manager') }}">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="alert alert-info">
                {{ __('Tableau de bord maintenance & issues (10,000 km + checklists quotidiennes).') }}
            </div>
        </div>
    </div>

    @if(!empty($alerts) && $alerts->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <strong>{{ __('Alertes récentes') }}</strong>
            </div>
            <div class="card-body">
                @foreach($alerts as $alert)
                    <div class="border rounded p-2 mb-2">
                        <div class="fw-bold">
                            {{ $alert->type }} -
                            {{ $alert->truck?->matricule ?? __('N/A') }}
                        </div>
                        <div class="text-muted" style="white-space: pre-wrap;">
                            {{ $alert->message }}
                        </div>
                        <small class="text-muted">{{ $alert->created_at->format('d/m/Y H:i') }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <strong>{{ __('Camions dues (10,000 km)') }}</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Matricule') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dueEngineTrucks as $truck)
                                <tr>
                                    <td class="fw-bold">{{ $truck->matricule }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary open-maintenance-modal"
                                            data-title="Maintenance 10,000 km - {{ $truck->matricule }}"
                                            data-route="{{ route('trucks.maintenances.create', $truck->id) }}"
                                        >
                                            <i class="la la-wrench"></i> {{ __('Faire maintenance') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">
                                        {{ __('Aucun camion en attente pour le moment.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <strong>{{ __('Issues checklists quotidiennes (non resolues)') }}</strong>
                </div>
                <div class="card-body">
                    @forelse($unresolvedIssues as $issue)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div>
                                    <div class="fw-bold">
                                        {{ $issue->category }} -
                                        {{ $issue->dailyChecklist->truck->matricule ?? '' }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $issue->dailyChecklist->checklist_date ?? '' }}
                                        {{ __('| Driver') }}: {{ $issue->dailyChecklist->driver->name ?? '' }}
                                    </small>
                                </div>
                            </div>

                            @if(!empty($issue->issue_notes))
                                <div class="mt-2">
                                    <strong>{{ __('Issue notes') }}:</strong>
                                    <div class="text-muted" style="white-space: pre-wrap;">
                                        {{ $issue->issue_notes }}
                                    </div>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('logistics.daily-issues.resolve', $issue->id) }}">
                                @csrf
                                <div class="mt-2">
                                    <label class="form-label">{{ __('Resolution notes') }}</label>
                                    <textarea name="resolution_notes" class="form-control" rows="2" required>{{ old('resolution_notes') }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success mt-2">
                                    <i class="la la-check"></i> {{ __('Marquer resolu') }}
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="text-muted">
                            {{ __('Aucune issue non resolue.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>{{ __('Historique checklists (dernieres 20)') }}</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Truck') }}</th>
                        <th>{{ __('Driver') }}</th>
                        <th>{{ __('Issues') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lastDailyChecklists as $check)
                        <tr>
                            <td>{{ $check->checklist_date }}</td>
                            <td class="fw-bold">{{ $check->truck->matricule ?? '' }}</td>
                            <td>{{ $check->driver->name ?? '' }}</td>
                            <td>
                                {{ $check->issues->count() }}
                                {{ $check->issues->count() > 1 ? __('issues') : __('issue') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">{{ __('Aucune donnée.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.open-maintenance-modal');
                if (!button) {
                    return;
                }

                showModal({
                    title: button.dataset.title,
                    route: button.dataset.route,
                    size: 'md'
                });
            });
        </script>
    @endpush
</x-layouts.main>

