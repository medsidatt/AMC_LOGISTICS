<div class="">

    <h4 class="mb-2">
        <i class="fas fa-industry text-primary"></i>
        Fournisseur – {{ $provider->name }}
    </h4>

    <div class="mb-3">
        <table class="table table-bordered mb-0">
            <tr><th>Nom</th><td>{{ $provider->name }}</td></tr>
            <tr><th>Téléphone</th><td>{{ $provider->phone ?? '—' }}</td></tr>
            <tr><th>Email</th><td>{{ $provider->email ?? '—' }}</td></tr>
            <tr><th>Adresse</th><td>{{ $provider->address ?? '—' }}</td></tr>
            <tr><th>Site web</th><td>{{ $provider->website ?? '—' }}</td></tr>
        </table>
    </div>

    <h5><i class="fas fa-route"></i> Derniers Trackings</h5>
    @if($provider->transportTrackings()->count() > 0)
        <ul class="list-group">
            @foreach($provider->transportTrackings()->latest()->take(5)->get() as $t)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <a href="javascript:void(0)"
                           onclick="showModal({
                               title: 'Détails {{ addslashes($t->reference) }}',
                               route: '{{ route('transport_tracking.show', $t->id) }}',
                               size: 'xl'
                           })"
                           class="text-primary">
                            {{ $t->reference }}
                        </a>
                        – {{ $t->client_date ? \Carbon\Carbon::parse($t->client_date)->format('d/m/Y') : '—' }}
                    </span>
                    <span class="badge bg-secondary">{{ $t->product ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-muted">Aucun tracking enregistré.</p>
    @endif

</div>
