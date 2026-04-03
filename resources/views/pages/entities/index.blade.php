<x-layouts.main
    title="{{ __('global.entities') }}"
    :actions="$actions"
>
    <div class="card">
        <div class="card-body table-responsive">
            <table
                class="table table-striped table-bordered w-100 dt-responsive nowrap"
                data-url="{{ route('entities.index') }}"
                data-column='logo,name,address,phone,email,website,actions'
            >
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('global.name') }}</th>
                    <th>{{ __('global.address') }}</th>
                    <th>{{ __('global.phone') }}</th>
                    <th>{{ __('global.email') }}</th>
                    <th>{{ __('global.website') }}</th>
                    <th>{{ __('global.actions') }}</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
</x-layouts.main>
