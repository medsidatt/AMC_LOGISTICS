<x-layouts.main :title="__('Mes voyages')">

    <div class="mb-3">
        <div class="d-flex align-items-center">
            <i class="la la-user-circle text-primary mr-2" style="font-size: 1.5rem;"></i>
            <strong>{{ $driver->name }}</strong>
            @if($truck)
                <span class="badge badge-primary ml-2"><i class="la la-truck mr-1"></i>{{ $truck->matricule }}</span>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong><i class="la la-route mr-1"></i>{{ __('Mes voyages') }}</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Ref') }}</th>
                        <th class="d-none d-md-table-cell">{{ __('Camion') }}</th>
                        <th>{{ __('Fournisseur') }}</th>
                        <th>{{ __('Produit') }}</th>
                        <th>{{ __('Poids (F)') }}</th>
                        <th>{{ __('Poids (C)') }}</th>
                        <th>{{ __('Ecart') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($trips as $trip)
                        <tr>
                            <td class="text-nowrap">{{ $trip->provider_date }}</td>
                            <td><small>{{ $trip->reference }}</small></td>
                            <td class="d-none d-md-table-cell">{{ $trip->truck?->matricule ?? '-' }}</td>
                            <td>{{ $trip->provider?->name ?? '-' }}</td>
                            <td><span class="badge badge-light">{{ $trip->product }}</span></td>
                            <td>{{ number_format($trip->provider_net_weight ?? 0, 0, '', ' ') }}</td>
                            <td>{{ number_format($trip->client_net_weight ?? 0, 0, '', ' ') }}</td>
                            <td>
                                @php $gap = $trip->gap ?? 0; @endphp
                                <span class="badge badge-{{ abs($gap) > 150 ? 'danger' : (abs($gap) > 50 ? 'warning' : 'success') }}">
                                    {{ number_format($gap, 0, '', ' ') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="la la-route" :message="__('Aucun voyage enregistre')" />
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($trips->hasPages())
        <div class="card-footer">
            {{ $trips->links() }}
        </div>
        @endif
    </div>

</x-layouts.main>
