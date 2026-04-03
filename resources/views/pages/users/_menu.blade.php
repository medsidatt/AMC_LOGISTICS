@hasAnyPermission('user-list|role-list')
<li class=" nav-item">
    <a href="#">
        <i class="la la-users"></i>
        <span class="menu-title">
            {{ __('Administration') }}
        </span></a>
    <ul class="menu-content">
        @can('user-list')
            <li class="{{ is_active(route('users.index')) }}">
                <a class="menu-item" href="{{ route('users.index') }}"><i></i>
                    <span>{{ __('Users') }}</span>
                </a>
            </li>
        @endcan
            @can('invitation-list')
            <li class="{{ is_active(route('invitation.index')) }}">
                <a class="menu-item" href="{{ route('invitation.index') }}"><i></i>
                    <span>{{ __('Invitations') }}</span>
                </a>
            </li>
        @endcan
        @can('role-list')
            <li class="{{ is_active(route('roles.index')) }}">
                <a class="menu-item" href="{{ route('roles.index') }}"><i></i>
                    <span>{{ __('Roles') }}</span>
                </a>
            </li>
        @endcan

    </ul>
</li>
@endhasAnyPermission
