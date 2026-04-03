<div class="">

    <h4 class="mb-2">
        <i class="fas fa-shipping-fast text-primary"></i>
        Transporteur – {{ $transporter->name }}
    </h4>

    <div class="mb-3">
        <table class="table table-bordered mb-0">
            <tr><th>Nom</th><td>{{ $transporter->name }}</td></tr>
            <tr><th>Téléphone</th><td>{{ $transporter->phone ?? '—' }}</td></tr>
            <tr><th>Email</th><td>{{ $transporter->email ?? '—' }}</td></tr>
            <tr><th>Adresse</th><td>{{ $transporter->address ?? '—' }}</td></tr>
            <tr><th>Site web</th><td>{{ $transporter->website ?? '—' }}</td></tr>
        </table>
    </div>

    <h5><i class="fas fa-truck"></i> Camions</h5>
    @if($transporter->trucks->count() > 0)
        <ul class="list-group">
            @foreach($transporter->trucks as $truck)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="javascript:void(0)"
                       onclick="showModal({
                           title: 'Détails Camion {{ addslashes($truck->matricule) }}',
                           route: '{{ route('trucks.show', $truck->id) }}',
                           size: 'xl'
                       })"
                       class="text-primary">
                        <i class="fas fa-truck fa-xs"></i> {{ $truck->matricule }}
                    </a>
                    @php $level = $truck->maintenanceLevel(); @endphp
                    <span class="badge bg-{{ $level === 'red' ? 'danger' : ($level === 'yellow' ? 'warning' : 'success') }}">
                        {{ $truck->remainingRotations() }} rotations restantes
                    </span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-muted">Aucun camion enregistré.</p>
    @endif

</div>
