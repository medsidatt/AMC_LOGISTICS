<x-layouts.guest>
    {{--<div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('Register') }}</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="row mb-3">
                                <label for="name" class="col-md-4 col-form-label text-md-end">{{ __('Name') }}</label>

                                <div class="col-md-6">
                                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                    @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('Email Address') }}</label>

                                <div class="col-md-6">
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                                    @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>

                                <div class="col-md-6">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                                    @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-end">{{ __('Confirm Password') }}</label>

                                <div class="col-md-6">
                                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                                </div>
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Register') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>--}}
    <section class="row navbar-flexbox-container">
        <div class="col-12 d-flex align-items-center justify-content-center">
            <div class="col-lg-4 col-md-8 col-10 box-shadow-2 p-0">
                <div class="card border-grey border-lighten-3 m-0">
                    <div class="card-header border-0 pb-0">
                        <div class="card-title text-center">
                            <h2>{{ config('app.name') }}</h2>
{{--                            <img src="{{asset('app-assets/images/logo/logo-dark.png')}}" alt="branding logo">--}}
                        </div>
                        <h6 class="card-subtitle line-on-side text-muted text-center font-small-3 pt-2"><span>Please Sign Up</span>
                        </h6>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <form class="form-horizontal" action="{{ route('invitation.register') }}"
                                  method="POST" novalidate>
                                @csrf
                                <input type="hidden" name="token" value="{{ $token ?? '' }}"/>
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-md-6">
                                        <fieldset class="form-group position-relative has-icon-left">
                                            <input
                                                value="{{ old('first_name') }}"
                                                type="text" name="first_name" id="first_name"
                                                   class="form-control @error('first_name') is-invalid @enderror"
                                                   placeholder="First Name" tabindex="1">
                                            @error('first_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-control-position">
                                                <i class="la la-user"></i>
                                            </div>
                                        </fieldset>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-6">
                                        <fieldset class="form-group position-relative has-icon-left">
                                            <input
                                                value="{{ old('last_name') }}"
                                                type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror"
                                                   placeholder="Last Name" tabindex="2">
                                            @error('last_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-control-position">
                                                <i class="la la-user"></i>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <fieldset class="form-group position-relative has-icon-left">
                                    <input type="number" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror"
                                           value="{{ old('phone') }}"
                                           placeholder="{{ __('employee.phone') }}" tabindex="4"
                                           data-validation-required-message="">
                                    @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-control-position">
                                        <i class="la la-phone"></i>
                                    </div>

                                </fieldset>
                                {{--<fieldset class="form-group position-relative has-icon-left">
                                    <input type="email" name="email" id="email" class="form-control"
                                           readonly
                                           value="{{ $email ?? '' }}"
                                           placeholder="Email Address" tabindex="4" required
                                           data-validation-required-message="Please enter email address.">
                                    <div class="form-control-position">
                                        <i class="la la-envelope"></i>
                                    </div>
                                    <div class="help-block font-small-3"></div>
                                </fieldset>--}}
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-md-6">
                                        <fieldset class="form-group position-relative has-icon-left">
                                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror"
                                                   placeholder="Password" tabindex="5" required>
                                            @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-control-position">
                                                <i class="la la-key"></i>
                                            </div>
                                            <div class="help-block font-small-3"></div>
                                        </fieldset>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-6">
                                        <fieldset class="form-group position-relative has-icon-left">
                                            <input type="password" name="password_confirmation"
                                                   id="password_confirmation" class="form-control"
                                                   placeholder="Confirm Password" tabindex="6"
                                                   data-validation-matches-match="password"
                                                   data-validation-matches-message="Password & Confirm Password must be the same.">
                                            <div class="form-control-position">
                                                <i class="la la-key"></i>
                                            </div>
                                            <div class="help-block font-small-3"></div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-4 col-sm-3 col-md-3">
                                        <fieldset>
                                            <input type="checkbox" id="accept-terms" class="chk-remember">
                                            <label for="remember-me"> I Agree</label>
                                        </fieldset>
                                    </div>
                                    <div class="col-8 col-sm-9 col-md-9">
                                        <p class="font-small-3">
                                            By clicking Register, you agree to the
                                            <a href="#"
                                               data-toggle="modal"
                                               data-target="#t_and_c_m">Terms
                                                and Conditions</a> set out by this site, including our Cookie Use.
                                        </p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <button
                                            id="register"
                                            type="button"
                                            onclick="function acceptTerms() {
                                                    if (document.getElementById('accept-terms').checked) {
                                                    document.getElementById('register').type = 'submit';
                                                    } else {
                                                    alert('Please accept the terms and conditions to continue');
                                                    }
                                                }
                                                acceptTerms()"
                                            class="btn btn-info btn-lg btn-block"><i
                                                class="la la-user"></i>
                                            Register
                                        </button>
                                    </div>
                                   {{-- <div class="col-12 col-sm-6 col-md-6">
                                        <a href="{{ route('login') }}" class="btn btn-danger btn-lg btn-block"><i
                                                class="ft-unlock"></i>
                                            Login</a>
                                    </div>--}}
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.guest>
