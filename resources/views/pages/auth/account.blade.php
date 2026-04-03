<x-layouts.main
    :title="__('My Account')"
>
    <section>
        <div class="row">
            <div class="col-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h4 class="card-title text-center">
                            {{ __('user.account') }}
                        </h4>
                        <button class="btn btn-primary" onclick="editAccount()">
                            {{ __('Edit') }}
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="card-body" id="edit-account-form">
                            <form action="{{ route("auth.account.update") }}" method="post">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <x-forms.input
                                        class="col-md-6"
                                        required
                                        :label="__('user.name')"
                                        name="name"
                                        :value="auth()->user()->name"
                                        disabled="disabled"
                                    />

                                    <x-forms.input
                                        class="col-md-6"
                                        required
                                        :label="__('user.email')"
                                        name="email"
                                        :value="auth()->user()->email"
                                        disabled="disabled"
                                    />

                                    <x-forms.input
                                        class="col-md-6"
                                        required
                                        :label="__('user.password')"
                                        name="password"
                                        type="password"
                                        disabled="disabled"
                                    />

                                    <div class="col-md-12">
                                        <x-buttons.save
                                            container="edit-account-form"
                                            onclick="saveForm({ element: this })"
                                        />
                                    </div>
                                </div>
                            </form>


                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-center">
                        <img  src="{{ auth()->user()->avatar ? asset('images/auth/' . auth()->user()->avatar) : 'https://www.gravatar.com/avatar/' . md5(auth()->user()->name) . '?d=mp' }}"
                             alt="avatar" class="img-fluid rounded-circle">
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <h4 class="card-title text-center">
                                {{ auth()->user()->name }}
                            </h4>
                            <p class="card-text text-center">
                                {{ auth()->user()->email }}
                            </p>
                            <p class="card-text text-center">
                                {{ auth()->user()->roles->first()->name }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function editAccount() {
            $('#name').prop('disabled', false);
            $('#email').prop('disabled', false);
            $('#password').prop('disabled', false);
        }
    </script>
</x-layouts.main>
