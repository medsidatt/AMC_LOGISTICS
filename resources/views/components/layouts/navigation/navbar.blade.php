<!-- BEGIN: Header-->
<nav class="header-navbar navbar-expand-md navbar navbar-with-menu navbar-without-dd-arrow fixed-top {{--navbar-semi-light--}}  navbar-semi-dark navbar-shadow">
    <div class="navbar-wrapper">
        <div class="navbar-header">
            <ul class="nav navbar-nav flex-row">
                <li class="nav-item mobile-menu d-md-none mr-auto">
                    <a class="nav-link nav-menu-main menu-toggle hidden-xs"
                       href="#">
                        <i class="ft-menu font-large-1"></i></a></li>
                <li class="nav-item"><a class="navbar-brand" href="{{route('home')}}">
                        <img class="brand-logo" alt="modern admin logo"
                             src="{{asset('app-assets/images/logo/logo.png')}}">
                        <h5 class="brand-text">
                            {{ config('app.name') }}
                        </h5>
                    </a></li>
                <li class="nav-item d-md-none"><a class="nav-link open-navbar-container" data-toggle="collapse"
                                                  data-target="#navbar-mobile"><i class="la la-ellipsis-v"></i></a></li>
            </ul>
        </div>
        <div class="navbar-container content">
            <div class="collapse navbar-collapse" id="navbar-mobile">
                <ul class="nav navbar-nav mr-auto float-left">
                    <li class="nav-item d-none d-md-block"><a class="nav-link nav-menu-main menu-toggle hidden-xs"
                                                              href="#"><i class="ft-menu"></i></a></li>
                    <li class="nav-item d-none d-lg-block"><a class="nav-link nav-link-expand" href="#"><i
                                class="ficon ft-maximize"></i></a></li>
                </ul>
                <ul class="nav navbar-nav float-right">
                    <li class="dropdown dropdown-language nav-item">
                        <a class="dropdown-toggle nav-link"
                           id="dropdown-flag" href="#"
                           data-toggle="dropdown" aria-haspopup="true"
                           aria-expanded="false">
                            <i class="flag-icon flag-icon-{{ app()->getLocale() == 'en' ? 'us' : 'fr' }}"></i>
                            <span class="selected-language"></span>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="dropdown-flag">
                            <a class="dropdown-item" href="{{ route('language.switch', ['locale' => 'en']) }}"
                               data-language="en"><i
                                    class="flag-icon flag-icon-us"></i> English</a>
                            <a class="dropdown-item" href="{{ route('language.switch', ['locale' => 'fr']) }}"
                               data-language="fr"><i
                                    class="flag-icon flag-icon-fr"></i> French</a>
                        </div>
                    </li>
                    <li class="dropdown dropdown-notification nav-item"><a class="nav-link nav-link-label" href="#"
                                                                           data-toggle="dropdown"><i
                                class="ficon ft-bell"></i><span
                                class="badge badge-pill badge-danger badge-up badge-glow">0</span></a>
                        <ul class="dropdown-menu dropdown-menu-media dropdown-menu-right">
                            <li class="dropdown-menu-header">
                                <h6 class="dropdown-header m-0"><span class="grey darken-2">Notifications</span></h6>
                                <span class="notification-tag badge badge-danger float-right m-0">0 New</span>
                            </li>
                            {{--<li class="scrollable-container media-list w-100">
                                <a href="javascript:void(0)">
                                    <div class="media">
                                        <div class="media-left align-self-center"><i
                                                class="ft-plus-square icon-bg-circle bg-cyan mr-0"></i></div>
                                        <div class="media-body">
                                            <h6 class="media-heading">You have new order!</h6>
                                            <p class="notification-text font-small-3 text-muted">Lorem ipsum dolor sit
                                                amet, consectetuer elit.</p><small>
                                                <time class="media-meta text-muted"
                                                      datetime="2015-06-11T18:29:20+08:00">30 minutes ago
                                                </time>
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            </li>--}}
                            {{--<li class="dropdown-menu-footer">
                                <a class="dropdown-item text-muted text-center"
                                   href="javascript:void(0)">
                                    {{ __('Read all notifications') }}
                                </a>
                            </li>--}}
                        </ul>
                    </li>
                    <li class="dropdown dropdown-user nav-item">
                        <a class="dropdown-toggle nav-link dropdown-user-link" href="#" data-toggle="dropdown">
                            <span class="mr-1 user-name text-bold-700">
                                {{ auth()->user()->name ?? '' }}
                            </span>
                            <span class="avatar avatar-online">
                                @auth()
                                <img src="{{ auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : 'https://www.gravatar.com/avatar/' . md5(auth()->user()->name) . '?d=mp' }}"
                                     alt="avatar">
                                @endauth
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{ route('auth.profile') }}"><i class="ft-user"></i>
                                {{ __('user.profile') }}
                            </a>
                            <a class="dropdown-item" href="{{ route('auth.account') }}"><i class="ft-user"></i>
                                {{ __('user.account') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="logOut()">
                                <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                      style="display: none;">
                                    @csrf
                                </form>
                                <i class="ft-power"></i>
                                {{ __('Logout') }}
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<!-- END: Header-->

{{--<script>
    function logout() {
        $.confirm({
            useBootstrap: true,
            title: '{{ __('Logout') }}',
            content: '{{ __('Are you sure you want to logout?') }}',
            buttons: {
                confirm: {
                    text: '{{ __('Logout') }}',
                    btnClass: 'btn-danger',
                    action: function () {
                        $('#logout-form').submit();
                    }
                },
                cancel: {
                    text: '{{ __('Cancel') }}',
                    action: function () {
                    }
                }
            }
        });
    }
</script>--}}

