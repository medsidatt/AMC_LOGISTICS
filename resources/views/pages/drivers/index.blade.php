<x-layouts.main
    title="{{ __('Conducteurs') }}"
    :actions="$actions"
>
    <div class="card">
        <div class="card-body">
            <table
                class="table table-striped table-bordered w-100"
                data-url="{{ route('drivers.index') }}"
                data-column='id,name,phone,email,address,actions'
            >
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Nom') }}</th>
                    <th>{{ __('Téléphone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Adresse') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
</x-layouts.main>
