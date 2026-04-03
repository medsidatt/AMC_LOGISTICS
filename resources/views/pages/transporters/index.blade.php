<x-layouts.main
    title="{{ __('Transporteurs') }}"
    :actions="$actions"
>
    <div class="card">
        <div class="card-body table-responsive">
            <table
                class="table table-striped table-bordered w-100 dt-responsive nowrap"
                data-url="{{ route('transporters.index') }}"
                data-column='id,name,phone,email,address,website,actions'
            >
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Nom') }}</th>
                    <th>{{ __('Téléphone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Adresse') }}</th>
                    <th>{{ __('Site Web') }}</th>
                    <th>{{ __('system.actions') }}</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
</x-layouts.main>
