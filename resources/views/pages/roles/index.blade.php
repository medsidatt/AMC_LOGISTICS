<x-layouts.main
    :title="__('user.roles')"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions"
>
    <section class="">
        <div class="card">
            <div class="card-body">
                <table class="table table-striped table-bordered w-100"
                    data-column="id,name,permissions,action"
                       data-url="{{ route('roles.index') }}"
                >
                    <thead>
                    <tr>
                        <th>{{ __('#No') }}</th>
                        <th>{{ __('Name')}}</th>
                        <th>{{ __('Permissions')}}</th>
                        <th>{{ __('Actions')}}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
</x-layouts.main>
