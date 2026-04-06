@php
    $isDriver = auth()->check() && auth()->user()->hasRole('Driver');
    $isSuperAdmin = auth()->check() && auth()->user()->hasRole('Super Admin');
    $isAdmin = auth()->check() && (auth()->user()->hasRole('Admin') || $isSuperAdmin);
@endphp

<div class="main-menu menu-fixed menu-dark menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="main-menu-content">
        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

            {{-- Dashboard --}}
            <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                <a href="{{ route('home') }}">
                    <i class="la la-home"></i>
                    <span class="menu-title">{{ __('Dashboard') }}</span>
                </a>
            </li>

            {{-- ══════════ DRIVER ══════════ --}}
            @if($isDriver)

            <li class="navigation-header"><span>{{ __('Mon espace') }}</span></li>
            <li class="nav-item {{ request()->routeIs('drivers.checklist-page') ? 'active' : '' }}">
                <a href="{{ route('drivers.checklist-page') }}">
                    <i class="la la-check-square"></i>
                    <span class="menu-title">{{ __('Checklist quotidien') }}</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('drivers.my-trips') ? 'active' : '' }}">
                <a href="{{ route('drivers.my-trips') }}">
                    <i class="la la-route"></i>
                    <span class="menu-title">{{ __('Mes voyages') }}</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('drivers.my-truck') ? 'active' : '' }}">
                <a href="{{ route('drivers.my-truck') }}">
                    <i class="la la-truck"></i>
                    <span class="menu-title">{{ __('Mon camion') }}</span>
                </a>
            </li>

            @else
            {{-- ══════════ ADMIN / SUPER ADMIN ══════════ --}}

            {{-- Transport --}}
            <li class="navigation-header"><span>{{ __('Transport') }}</span></li>
            <li class="nav-item {{ request()->routeIs('transport_tracking.index') ? 'active' : '' }}">
                <a href="{{ route('transport_tracking.index') }}">
                    <i class="la la-list"></i>
                    <span class="menu-title">{{ __('Suivi Transport') }}</span>
                </a>
            </li>
{{--            <li class="nav-item {{ request()->routeIs('transport_tracking.dashboard') ? 'active' : '' }}">--}}
{{--                <a href="{{ route('transport_tracking.dashboard') }}">--}}
{{--                    <i class="la la-chart-bar"></i>--}}
{{--                    <span class="menu-title">{{ __('Dashboard Analytics') }}</span>--}}
{{--                </a>--}}
{{--            </li>--}}
            <li class="nav-item {{ is_active(route('providers.index')) }}">
                <a href="{{ route('providers.index') }}">
                    <i class="la la-industry"></i>
                    <span class="menu-title">{{ __('Fournisseurs') }}</span>
                </a>
            </li>

            {{-- Fleet --}}
            <li class="navigation-header"><span>{{ __('Flotte') }}</span></li>
            <li class="nav-item {{ is_active(route('trucks.index')) }}">
                <a href="{{ route('trucks.index') }}">
                    <i class="la la-truck"></i>
                    <span class="menu-title">{{ __('Camions') }}</span>
                </a>
            </li>
            <li class="nav-item {{ is_active(route('drivers.index')) }}">
                <a href="{{ route('drivers.index') }}">
                    <i class="la la-id-card"></i>
                    <span class="menu-title">{{ __('Conducteurs') }}</span>
                </a>
            </li>
            <li class="nav-item {{ is_active(route('transporters.index')) }}">
                <a href="{{ route('transporters.index') }}">
                    <i class="la la-sitemap"></i>
                    <span class="menu-title">{{ __('Transporteurs') }}</span>
                </a>
            </li>

            {{-- Maintenance --}}
            <li class="navigation-header"><span>{{ __('Maintenance') }}</span></li>
            <li class="nav-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
                <a href="{{ route('logistics.dashboard') }}">
                    <i class="la la-wrench"></i>
                    <span class="menu-title">{{ __('Tableau de bord') }}</span>
                </a>
            </li>

            {{-- Administration --}}
            <li class="navigation-header"><span>{{ __('Administration') }}</span></li>
            <li class="nav-item {{ is_active(route('users.index')) }}">
                <a href="{{ route('users.index') }}">
                    <i class="la la-users"></i>
                    <span class="menu-title">{{ __('Utilisateurs') }}</span>
                </a>
            </li>
            <li class="nav-item {{ request()->routeIs('invitation.*') ? 'active' : '' }}">
                <a href="{{ route('invitation.index') }}">
                    <i class="la la-envelope"></i>
                    <span class="menu-title">{{ __('Invitations') }}</span>
                </a>
            </li>
            @if($isSuperAdmin)
            <li class="nav-item {{ is_active(route('roles.index')) }}">
                <a href="{{ route('roles.index') }}">
                    <i class="la la-shield-alt"></i>
                    <span class="menu-title">{{ __('Roles') }}</span>
                </a>
            </li>
            @endif
            <li class="nav-item {{ is_active(route('projects.index')) }}">
                <a href="{{ route('projects.index') }}">
                    <i class="la la-folder-open"></i>
                    <span class="menu-title">{{ __('Projets') }}</span>
                </a>
            </li>
            <li class="nav-item {{ is_active(route('entities.index')) }}">
                <a href="{{ route('entities.index') }}">
                    <i class="la la-building"></i>
                    <span class="menu-title">{{ __('Entites') }}</span>
                </a>
            </li>

            @endif

        </ul>
    </div>
</div>
