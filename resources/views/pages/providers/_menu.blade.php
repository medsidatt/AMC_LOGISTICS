<li class="nav-item {{ request()->routeIs('transport_tracking.index') ? 'active' : '' }}">
    <a href="{{ route('transport_tracking.index') }}">
        <i class="la la-list"></i>
        <span class="menu-title">{{ __('Suivi Transport') }}</span>
    </a>
</li>

@role('Admin')
    <li class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
        <a href="{{ route('logistics.dashboard') }}">
            <i class="la la-chart-line"></i>
            <span class="menu-title">{{ __('Dashboard Logistics') }}</span>
        </a>
    </li>
@endrole

@role('Super Admin')
    <li class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
        <a href="{{ route('logistics.dashboard') }}">
            <i class="la la-chart-line"></i>
            <span class="menu-title">{{ __('Dashboard Logistics') }}</span>
        </a>
    </li>
@endrole
<li class="nav-item {{ is_active(route('providers.index')) }}">
    <a href="{{ route('providers.index') }}">
        <i class="la la-industry"></i>
        <span class="menu-title">{{ __('Fournisseurs') }}</span>
    </a>
</li>
<li class="nav-item {{ is_active(route('transporters.index')) }}">
    <a href="{{ route('transporters.index') }}">
        <i class="la la-sitemap"></i>
        <span class="menu-title">{{ __('Transporteurs') }}</span>
    </a>
</li>
<li class="nav-item {{ is_active(route('trucks.index')) }}">
    <a href="{{ route('trucks.index') }}">
        <i class="la la-truck"></i>
        <span class="menu-title">{{ __('Camions') }}</span>
    </a>
</li>
<li class="nav-item {{ is_active(route('drivers.index')) }}">
    <a href="{{ route('drivers.index') }}">
        <i class="la la-user"></i>
        <span class="menu-title">{{ __('Conducteurs') }}</span>
    </a>
</li>
@role('Driver')
    <li class="nav-item {{ request()->routeIs('drivers.checklist-page') ? 'active' : '' }}">
        <a href="{{ route('drivers.checklist-page') }}">
            <i class="la la-check-square"></i>
            <span class="menu-title">{{ __('Checklist Driver') }}</span>
        </a>
    </li>
@endrole
