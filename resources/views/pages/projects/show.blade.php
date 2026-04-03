<x-layouts.main
    :title="__('Details of :project', ['project' => $project->name])"
    :actions="$actions"
    :breadcrumbs="$breadcrumbs"
>
    <div class="card">
        <div class="card-header bg-light">
            <strong>{{ $project->name }} ({{ $project->code }})</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th class="text-muted">{{ __('global.name') }}</th>
                            <td>{{ $project->name }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('Code') }}</th>
                            <td>{{ $project->code }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('global.entity') }}</th>
                            <td>{{ $project->entity->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('Description') }}</th>
                            <td>{{ $project->description ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th class="text-muted">{{ __('global.start_date') }}</th>
                            <td>{{ $project->start_date ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('global.end_date') }}</th>
                            <td>{{ $project->end_date ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('global.phone') }}</th>
                            <td>{{ $project->phone ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('global.email') }}</th>
                            <td>{{ $project->email ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($project->users->count())
            <hr>
            <h6>{{ __('Utilisateurs assignes') }}</h6>
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>{{ __('global.name') }}</th>
                        <th>{{ __('global.email') }}</th>
                        <th>{{ __('Role') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project->users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ ucfirst($user->pivot->role) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</x-layouts.main>
