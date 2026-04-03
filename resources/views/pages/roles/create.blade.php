<x-layouts.main
    :title="__('user.create_role')"
    :breadcrumbs="$breadcrumbs"
>
    <section class="">
        <div class="card">
            <div class="card-body">

                @if (session('success'))
                    <div class="alert alert-success">
                        <strong>{{ session('success') }}</strong>
                    </div>
                @endif
                <form action="{{ route('roles.store') }}" method="post">
                    @csrf
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="row">
                                <div class="col">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Name:</label>
                                        <input type="text" name="name" value="{{ old('name') }}"
                                               class="form-control @error('name') is-invalid @enderror">
                                        @error('name')
                                        <strong class="invalid-feedback">{{ $message }}</strong>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="mb-3">
                                        <label for="guard" class="form-label">{{ __('Guard') }}:</label>
                                        <select
                                            data-placeholder="Select"
                                            name="guard" id="guard" class="form-select select2">
                                            @foreach(config('auth.guards') as $guardName => $guard)
                                                <option value="{{ $guardName }}">{{ $guardName }}</option>
                                            @endforeach
                                        </select>
                                        @error('name')
                                        <strong class="invalid-feedback">{{ $message }}</strong>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mt-2 fw-bolder ">{{__('user.permissions')}}: </h4>
                                <div class="float-end">
                                    <button type="submit" class="btn btn-success">
                                        {{ __('global.save') }}
                                        <i class="bi bi-check"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                @php
                                    $getGroupPermission = function ($permission) {
                                        return ucfirst(explode('-', $permission->name)[0]);
                                    };
                                @endphp
                                <div class="col">
                                    <ul class="list-group list-group-flush">
                                        @foreach($permission->groupBy($getGroupPermission) as $key => $value)
                                            <i class="list-group-item">
                                                <div class="row">
                                                    <div class="col-md-12 gy-3">
                                                        <h5 class="fw-bolder">
                                                            <strong>{{ ucfirst($key) }}</strong>
                                                        </h5>
                                                        <div class="row">
                                                            @foreach($value as $item)
                                                                <div class="col-md-3">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input"
                                                                               name="permissions[]"
                                                                               type="checkbox"
                                                                               value="{{ $item->id }}"
                                                                               id="flexCheckDefault-{{ $item->id }}">
                                                                        <label class="form-check-label"
                                                                               for="flexCheckDefault-{{ $item->id }}">
                                                                            @for($i = 0; $i < count(explode('-', $item->name)) ; $i++)
                                                                                {{ ucfirst(explode('-', $item->name)[$i]) }}
                                                                            @endfor
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>

                                                    </div>
                                                </div>
                                            </i>

                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <div class="float-end">
                                <button type="submit" class="btn btn-success">
                                    {{ __('global.create') }}
                                    <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </li>
                    </ul>

                </form>
            </div>
        </div>
    </section>
</x-layouts.main>
