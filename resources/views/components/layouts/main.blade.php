<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="ltr">
<!-- BEGIN: Head-->

<head>
    {{--    csrf--}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description"
          content="">
    <meta name="keywords"
          content="amc, consulting, amc consulting, amc-travaux, travaux, sogeco, effage, induproj, gta">
    <meta name="author" content="AMC CONSULTING">
    <title>{{ config('app.name') }} - {{ $title ? $title : 'AMC' }}</title>
    {{--    <link rel="apple-touch-icon" href="{{asset('app-assets/images/ico/apple-icon-120.png')}}">--}}
        <link rel="shortcut icon" type="image/x-icon" href="{{asset('app-assets/images/ico/favicon.ico')}}">
    <link
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i%7CQuicksand:300,400,500,700"
        rel="stylesheet">
    <link rel= "stylesheet" href= "https://maxst.icons8.com/vue-static/landings/line-awesome/font-awesome-line-awesome/css/all.min.css" >

    <!-- BEGIN: Vendor CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/vendors.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/ui/prism.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/forms/selects/select2.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/extensions/sweetalert2.min.css')}}">
    <link rel="stylesheet" type="text/css"
          href="{{asset('app-assets/vendors/css/tables/datatable/dataTables.bootstrap4.min.css')}}">
    <link rel="stylesheet" type="text/css"
          href="{{asset('app-assets/vendors/css/tables/datatable/datatables.min.css')}}">
    <link rel="stylesheet" type="text/css"
          href="{{asset('app-assets/vendors/css/tables/extensions/responsive.dataTables.min.css')}}">
    <!-- END: Vendor CSS-->

    <!-- BEGIN: Theme CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/bootstrap.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/bootstrap-extended.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/colors.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/components.css')}}">



    <!-- END: Theme CSS-->

    <!-- BEGIN: Page CSS-->
    <link rel="stylesheet" type="text/css"
          href="{{asset('app-assets/css/core/menu/menu-types/vertical-menu.css')}}">

    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/core/colors/palette-gradient.css')}}">
    <!-- END: Page CSS-->

    <!-- BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/css/style.css')}}">
    <!-- END: Custom CSS-->

    {{--    @vite('resources/sass/app.scss')--}}

    <style>
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
        }
        .overlay.show {
            display: flex;
        }
    </style>

</head>
<!-- END: Head-->

<!-- BEGIN: Body-->

<body class="vertical-layout vertical-menu 2-columns fixed-navbar" data-open="click" data-menu="vertical-menu"
      data-col="2-columns">

<!-- BEGIN: Header-->
<x-layouts.navigation.navbar/>
<!-- END: Header-->
<!-- BEGIN: Main Menu-->
<x-layouts.navigation.sidebar/>
<!-- END: Main Menu-->

<!-- BEGIN: Content-->
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        <div class="content-header row">
            <div class="content-header-left col-md-6 col-12 mb-2">
                @if(isset($title))
                    <h3 class="content-header-title">
                        {{ $title }}
                    </h3>
                @endif
                <div class="row breadcrumbs-top">
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="/">{{ __('system.home') }}</a>
                            </li>
                            @if (!empty($breadcrumbs))
                                @foreach($breadcrumbs as $index => $breadcrumb)
                                    <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                                        @if (!$loop->last)
                                            <a href="{{ $breadcrumb['url'] ?? '#' }}">
                                                {{ $breadcrumb['label'] }}
                                            </a>
                                        @else
                                            {{ $breadcrumb['label'] }}

                                        @endif
                                    </li>
                                @endforeach
                            @else
                                <li class="breadcrumb-item active">
                                    {{ $title ?? '' }}
                                </li>
                            @endif
                        </ol>
                    </div>
                </div>
            </div>
            <div class="content-header-right col-md-6 col-12">
                @if(!empty($actions))
                    @php
                        $filteredActions = array_filter($actions, function ($action) {
                            return (is_bool($action['permission']) && $action['permission']) || (\Illuminate\Support\Facades\Gate::allows($action['permission']));
                        });
                    @endphp
                    @if(!empty($filteredActions))
                        @if(count($filteredActions) > 1)
                            <div class="btn-group float-md-right" role="group"
                                 aria-label="Button group with nested dropdown">
                                <button
                                    class="btn btn-info dropdown-toggle dropdown-menu-right box-shadow-2 px-2 mb-1"
                                    id="btnGroupDrop" type="button" data-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    {{ __('Actions') }}
                                </button>
                                <div class="dropdown-menu" aria-labelledby="btnGroupDrop">
                                    @foreach($filteredActions as $action)
                                        <a onclick="{{ $action['onclick'] ?? null }}"
                                           class="dropdown-item"
                                           href="{{ $action['url'] ?? '#' }}"
                                        >
                                            {{ isset($action['label']) ? $action['label'] : '' }}
                                            {{-- Display icon if exists --}}
{{--                                            {{ isset($action['icon']) ? '<i class="' . $action['icon'] . '"></i>' : '' }}--}}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a onclick="{{ $filteredActions[0]['onclick'] ?? null }}"
                               class="btn btn-info float-md-right box-shadow-2 px-2 mb-1"
                               href="{{ $filteredActions[0]['url'] ?? '#' }}"
                            >
                                {{ $filteredActions[0]['label'] ?? '' }}
                                {{-- Display icon if exists --}}
                                <i class="{{ $filteredActions[0]['icon'] ?? 'la la-plus' }}"></i>
                            </a>
                        @endif
                    @endif
                @endif
            </div>
        </div>
        <div class="content-body">
            {{ $slot }}
        </div>
    </div>
</div>
<!-- END: Content-->

<div class="sidenav-overlay"></div>
<div class="drag-target"></div>

<div class="overlay" id="spinnerOverlay">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
</div>

{{--<footer class="footer footer-static footer-light navbar-border navbar-shadow">
    <p class="clearfix blue-grey lighten-2 text-sm-center mb-0 px-2">
        <span class="float-md-left d-block d-md-inline-block">
            Copyright &copy; {{ now()->format('Y') }}
            <a class="text-bold-800 grey darken-2" href="https://amc4consulting.com" target="_blank">AMC CONSULTING</a>
        </span>
        <span class="float-md-right d-none d-lg-block">Hand-crafted & Made with<i class="ft-heart pink"></i> by IT Team
            <span id="scroll-top"></span>
        </span>
    </p>
</footer>--}}

<!-- ======= Modals ======= -->
@foreach (['main','first','second','third','fourth'] as $type_modal)
    <div id="{{$type_modal}}-modal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-body">

                </div>
            </div>
        </div>
    </div>
@endforeach

<!-- Button trigger modal -->

<!-- BEGIN: Vendor JS-->
<script src="{{ asset('app-assets/vendors/js/vendors.min.js') }}"></script>
<!-- BEGIN Vendor JS-->

<!-- BEGIN: Page Vendor JS-->
<script src="{{ asset('app-assets/vendors/js/ui/prism.min.js') }}"></script>
<!-- END: Page Vendor JS-->

<!-- BEGIN: Theme JS-->
<script src="{{ asset('app-assets/js/core/app-menu.js') }}"></script>
<script src="{{ asset('app-assets/js/core/app.js') }}"></script>

<script src="{{ asset('app-assets/vendors/js/forms/select/select2.full.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/datatable/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/datatable/dataTables.responsive.min.js') }}"></script>

<script src="{{asset('assets/js/scripts.js')}}"></script>
<!-- END: Theme JS-->

{{--@vite('resources/js/app.js')--}}

@if(session('error'))
    <script>
        Toast.fire({
            type: 'error',
            title: '{{ session('error') }}'
        })
    </script>
@endif

@if(session('success'))
    <script>
        Toast.fire({
            type: 'success',
            title: '{{ session('success') }}'
        })
    </script>
@endif


@stack('scripts')

<!-- BEGIN: Page JS-->
<!-- END: Page JS-->

</body>
<!-- END: Body-->

</html>
