<x-layouts.main
    :title="__('user.list_users')"
    :actions="$actions"
>
    <section>
        <div class="card">
            <div class="card-header">

            </div>
            <div class="card-body">
                <table
                    data-column="id,name,email,status,action"
                    data-url="{{ route('users.index') }}"
                    class="table table-striped table-bordered w-100"
                >
                    <thead>
                    <tr>
                        <th>#ID</th>
                        <th>{{ __('user.name') }}</th>
                        <th>{{ __('user.email') }}</th>
                        <th>{{ __('user.status') }}</th>
                        <th>{{ __('system.actions') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>

</x-layouts.main>

