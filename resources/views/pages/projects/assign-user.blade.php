<section class="mb-4">
    <ul class="list-group">
        @foreach($project->users as $user)
{{--            @dd($project->users)--}}
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    {{ $user->name }} ({{ $user->email }})
                    @if($user->pivot->role)
                        - <strong class="badge badge-info">{{ $user->pivot->role }}</strong>
                    @endif
                </span>
                <button
                    onclick="confirmDelete('{{ route('projects.assign.user.destroy', [$project->id, $user->id]) }}', 'dynamic',
                        function() {
                          showModal({
                            route: '{{ route('projects.assign.user', [$project->id]) }}',
                          });
                        }
                    )"
                    type="submit" class="btn btn-danger btn-sm">
                    <i class="fa fa-trash"></i>
                </button>
            </li>
        @endforeach
    </ul>
</section>

<section id="assign-project-form">
    <form action="{{ route('projects.assign.user.store', $project->id) }}" method="POST">
        @csrf
        <div class="row">
            <x-forms.select
                class="col-md-6"
                name="user_id"
                label="{{ __('global.user') }}"
                :options="$users"
                select-class="select2"
                data-parent="assign-project-form"
            />
            <div class="col-md-6">
                <label for="role" class="form-label">{{ __('global.role') }}</label>
                <select name="role" id="role" class="form-select select2"
                        data-placeholder="{{ __('global.select') }}"
                        data-parent="assign-project-form">
                    <option></option>
                    @foreach($roles as $role)
                        <option value="{{ $role['id']}}">{{ $role['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <x-buttons.save
            container="assign-project-form"
            onclick="saveForm({element: this, modal: 'dynamic'})"
        />
    </form>
</section>
