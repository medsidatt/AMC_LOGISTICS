<x-layouts.main
    :title="__('invitation.list_invitations')"
    :actions="$actions"
>
    <section>
        <div class="card">
            <div class="card-body table-responsive">
                <table
                    data-column="id,email,created_at,expires_at,action"
                    data-url="{{ route('invitation.index') }}"
                    class="table responsive w-100 dt-responsive nowrap"
                >
                    <thead>
                    <tr>
                        <th>#ID</th>
                        <th>{{ __('user.email') }}</th>
                        <th>{{ __('global.created_at') }}</th>
                        <th>{{ __('global.expires_at') }}</th>
                        <th>{{ __('system.actions') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
</x-layouts.main>
