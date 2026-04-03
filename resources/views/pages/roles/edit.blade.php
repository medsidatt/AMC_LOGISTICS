<x-layouts.main
    title="{{ __('user.role') }}"
    :breadcrumbs="[
        [
            'url' => route('roles.index'),
            'label' => __('Roles Management')
        ],
        [
            'url' => route('roles.edit', $role->id),
            'label' => __('Edit role :name', ['name' => $role->name])
        ]
    ]"
>
    <section class="">
        <div class="card">
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <form action="{{ route('roles.update',  $role->id ) }}" method="post">
                        @csrf
                        @method('PUT')
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <div class="row">
                                    <x-forms.input
                                        class="col-md-6"
                                        id="name"
                                        label="{{ __('Name') }}"
                                        name="name"
                                        :value="$role->name"
                                    />
                                    <div class="col-md-6">
                                            <label for="guard" class="form-label">{{ __('Guard') }}:</label>
                                            <select name="guard" id="guard" class="form-control select2">
                                                <option></option>
                                                @foreach(config('auth.guards') as $guardName => $guard)
                                                    <option value="{{ $guardName }}"
                                                            @if($guardName == $role->guard_name) selected @endif>{{ $guardName }}</option>
                                                @endforeach
                                            </select>
                                            @error('guard')
                                            <strong class="invalid-feedback">{{ $message }}</strong>
                                            @enderror
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row justify-content-between align-items-center">
                                    <div class="col">
                                        <h4 class="mt-2 fw-bolder ">{{__('Permissions')}}: </h4>
                                    </div>
                                    <div class="col d-flex justify-content-end">
                                        <button type="submit" class="btn btn-success me-2">
                                            {{ __('Save changes') }}
                                            <i class="bi bi-check-lg"></i>
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
                                                                                   type="checkbox"
                                                                                   name="permissions[]"
                                                                                   value="{{ $item->id }}"
                                                                                   id="flexCheckDefault-{{ $item->id }}"
                                                                                   @if(in_array($item->name, $role->permissions->pluck('name')->toArray())) checked @endif>
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
                            </li>
                        </ul>

                    </form>
                </ul>
            </div>
        </div>
    </section>
</x-layouts.main>
