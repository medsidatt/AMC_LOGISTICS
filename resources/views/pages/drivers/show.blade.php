{{--<section>

    <h5 class="mb-2">Chauffeur : {{ $driver->name }}</h5>

    <table class="table table-bordered">
        <tr>
            <th style="width: 30%">Name</th>
            <td>{{ $driver->name }}</td>
        </tr>
        <tr>
            <th>Phone</th>
            <td>{{ $driver->phone ?? '—' }}</td>
        </tr>
        <tr>
            <th>National ID</th>
            <td>{{ $driver->nid ?? '—' }}</td>
        </tr>
        <tr>
            <th>License Number</th>
            <td>{{ $driver->license ?? '—' }}</td>
        </tr>
        <tr>
            <th>Assigned Truck</th>
            <td>{{ $driver->truck?->matricule ?? '—' }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <span class="badge {{ $driver->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                    {{ ucfirst($driver->status) }}
                </span>
            </td>
        </tr>
    </table>

</section>--}}
<div class="">

    <h4 class="mb-2">
        <i class="fas fa-user-tie text-primary"></i>
        Chauffeur – {{ $driver->name }}
    </h4>

    <div class="mb-3">
        <div class="">
            <table class="table table-bordered mb-0">
                <tr><th>Nom</th><td>{{ $driver->name }}</td></tr>
                <tr><th>Téléphone</th><td>{{ $driver->phone ?? '—' }}</td></tr>
                <tr><th>Identifiant National</th><td>{{ $driver->nid ?? '—' }}</td></tr>
                <tr><th>Permis</th><td>{{ $driver->license ?? '—' }}</td></tr>
                <tr><th>Camion Assigné</th><td>{{ $driver->truck?->matricule ?? '—' }}</td></tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="badge {{ $driver->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                            {{ ucfirst($driver->status) }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <h5><i class="fas fa-route"></i> Derniers Trackings</h5>
    <ul class="list-group">
        @foreach($driver->transportTrackings()->latest()->take(5)->get() as $t)
            <li class="list-group-item">
                {{ $t->reference }} – {{ $t->date?->format('d/m/Y') }}
            </li>
        @endforeach
    </ul>

</div>
