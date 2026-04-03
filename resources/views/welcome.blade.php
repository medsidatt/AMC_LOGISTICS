<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="ltr">
<!-- BEGIN: Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="Modern admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities with bitcoin dashboard.">
    <meta name="keywords" content="admin template, modern admin template, dashboard template, flat admin template, responsive admin template, web app, crypto dashboard, bitcoin dashboard">
    <meta name="author" content="PIXINVENT">
    <title>Navigation Dark - Modern Admin - Clean Bootstrap 4 Dashboard HTML Template + Bitcoin Dashboard</title>
    <link rel="apple-touch-icon" href="{{asset('app-assets/images/ico/apple-icon-120.png')}}">
    <link rel="shortcut icon" type="image/x-icon" href="{{asset('app-assets/images/ico/favicon.ico')}}">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i%7CQuicksand:300,400,500,700" rel="stylesheet">

    <!-- BEGIN: Vendor CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/vendors.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/ui/prism.min.css')}}">
    <!-- END: Vendor CSS-->

    <!-- BEGIN: Theme CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/bootstrap.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/bootstrap-extended.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/colors.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/components.css')}}">
    <!-- END: Theme CSS-->

    <!-- BEGIN: Page CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/core/menu/menu-types/vertical-menu.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css/core/colors/palette-gradient.css')}}">
    <!-- END: Page CSS-->

    <!-- BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('assets/css/style.css')}}">
    <!-- END: Custom CSS-->

</head>
<!-- END: Head-->

<!-- BEGIN: Body-->

<body class="vertical-layout vertical-menu 2-columns fixed-navbar" data-open="click" data-menu="vertical-menu" data-col="2-columns">

<x-layouts.navigation.navbar />

<x-layouts.navigation.sidebar />

<!-- END: Main Menu-->
<!-- BEGIN: Content-->
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">
        <div class="content-header row">
            <div class="content-header-left col-md-6 col-12 mb-2">
                <h3 class="content-header-title">Navigation Dark</h3>
                <div class="row breadcrumbs-top">
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.html">Home</a>
                            </li>
                            <li class="breadcrumb-item"><a href="#">Navigation</a>
                            </li>
                            <li class="breadcrumb-item active">Navigation Dark
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="content-header-right col-md-6 col-12">
                <div class="btn-group float-md-right" role="group" aria-label="Button group with nested dropdown">
                    <button class="btn btn-info round dropdown-toggle dropdown-menu-right box-shadow-2 px-2 mb-1" id="btnGroupDrop1" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="ft-settings icon-left"></i> Settings</button>
                    <div class="dropdown-menu" aria-labelledby="btnGroupDrop1"><a class="dropdown-item" href="card-bootstrap.html">Cards</a><a class="dropdown-item" href="component-buttons-extended.html">Buttons</a></div>
                </div>
            </div>
        </div>
        <div class="content-body">
            <!-- Description -->
            <section id="description" class="card">
                <div class="card-header">
                    <h4 class="card-title">Description</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="card-text">
                            <p>For dark template, Modern Admin provides dark color vertical navigation menu options, you can check
                                dark color vertical navigation menu on left side.</p>
                        </div>
                        <div class="alert bg-success alert-icon-left mb-2" role="alert">
                            <span class="alert-icon"><i class="la la-pencil-square"></i></span>
                            <strong>Experience it!</strong>
                            <p>This page contain dark navigation menu options example, check at the left hand side of the page.</p>
                        </div>
                    </div>
                </div>
            </section>
            <!--/ Description -->
            <!-- CSS Classes -->
            <section id="css-classes" class="card">
                <div class="card-header">
                    <h4 class="card-title">CSS Classes</h4>
                    <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                    <div class="heading-elements">
                        <ul class="list-inline mb-0">
                            <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                            <li><a data-action="reload"><i class="ft-rotate-cw"></i></a></li>
                            <li><a data-action="close"><i class="ft-x"></i></a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-content collapse show">
                    <div class="card-body">
                        <div class="card-text">
                            <p>This table contains all classes related to the dark vertical navigation option.</p>
                            <p>All these options can be set via following classes:</p>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Classes</th>
                                        <th>Description</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <th scope="row"><code>.menu-dark</code></th>
                                        <td>To create dark vertical navigation, you need to add <code>.menu-dark</code> class
                                            in navigation <code>&lt;div&gt;</code> tag. Refer HTML markup line no 12.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!--/ CSS Classes -->
            <!-- HTML Markup -->
            <section id="html-markup" class="card">
                <div class="card-header">
                    <h4 class="card-title">HTML Markup</h4>
                    <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                    <div class="heading-elements">
                        <ul class="list-inline mb-0">
                            <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                            <li><a data-action="reload"><i class="ft-rotate-cw"></i></a></li>
                            <li><a data-action="close"><i class="ft-x"></i></a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-content collapse show">
                    <div class="card-body">
                        <div class="card-text">
                            <p>This section contains HTML Markup to create vertical dark navigation. This markup define where to
                                add css classes to make vertical dark navigation.</p>
                            <ul>
                                <li><span class="text-bold-600">Line no 12:</span> Contain the <code>.menu-dark</code> class to set
                                    vertical navigation color dark.</li>
                            </ul>
                            <pre data-line="12" class="language-markup">
                  <code class="language-markup">
                      &lt;!DOCTYPE html&gt;
                        &lt;html lang="en"&gt;
                          &lt;head&gt;&lt;/head&gt;
                          &lt;body data-menu="vertical-menu" class="vertical-layout vertical-menu 2-column menu-expanded"&gt;

                            &lt;!-- fixed-top--&gt;
                            &lt;nav role="navigation" class="header-navbar navbar-expand-sm navbar navbar-with-menu fixed-top navbar-dark navbar-shadow navbar-border"&gt;
                                ...
                            &lt;/nav&gt;

                            &lt;!-- BEGIN Navigation--&gt;
                            &lt;div class="main-menu menu-dark menu-shadow"&gt;
                                ...
                            &lt;/div&gt;
                            &lt;!-- END Navigation--&gt;

                            &lt;!-- BEGIN Content--&gt;
                            &lt;div class="content app-content"&gt;
                                &lt;div class="content-wrapper"&gt;
                                    &lt;!-- content header--&gt;
                                    &lt;div class="content-header row"&gt;
                                        ...
                                    &lt;/div&gt;
                                    &lt;!-- content header--&gt;

                                    &lt;!-- content body--&gt;
                                    &lt;div class="content-body"&gt;
                                        ...
                                    &lt;/div&gt;
                                    &lt;!-- content body--&gt;

                                &lt;/div&gt;
                            &lt;/div&gt;
                            &lt;!-- END Content--&gt;

                            &lt;!-- START FOOTER DARK--&gt;
                            &lt;footer class="footer footer-dark"&gt;
                                ...
                            &lt;/footer&gt;
                            &lt;!-- START FOOTER DARK--&gt;

                          &lt;/body&gt;
                        &lt;/html&gt;
                  </code>
                </pre>
                        </div>
                    </div>
                </div>
            </section>
            <!--/ HTML Markup -->
        </div>
    </div>
</div>
<!-- END: Content-->

<div class="sidenav-overlay"></div>
<div class="drag-target"></div>

<!-- BEGIN: Footer-->
<footer class="footer footer-static footer-light navbar-border navbar-shadow">
    <p class="clearfix blue-grey lighten-2 text-sm-center mb-0 px-2"><span class="float-md-left d-block d-md-inline-block">Copyright &copy; 2019 <a class="text-bold-800 grey darken-2" href="https://1.envato.market/modern_admin" target="_blank">PIXINVENT</a></span><span class="float-md-right d-none d-lg-block">Hand-crafted & Made with<i class="ft-heart pink"></i><span id="scroll-top"></span></span></p>
</footer>
<!-- END: Footer-->


<!-- BEGIN: Vendor JS-->
<script src="{{asset('app-assets/vendors/js/vendors.min.js')}}"></script>
<!-- BEGIN Vendor JS-->

<!-- BEGIN: Page Vendor JS-->
<script src="{{asset('app-assets/vendors/js/ui/prism.min.js')}}"></script>
<!-- END: Page Vendor JS-->

<!-- BEGIN: Theme JS-->
<script src="{{asset('app-assets/js/core/app-menu.js')}}"></script>
<script src="{{asset('app-assets/js/core/app.js')}}"></script>
<!-- END: Theme JS-->

<!-- BEGIN: Page JS-->
<!-- END: Page JS-->

</body>
<!-- END: Body-->

</html>
