@foreach($permissions as $permission)
    @if(array_key_exists($permission->id, $rolePermissions))
        <i class="la la-check-circle text-success"></i>
    @else
        <i class="la la-times-circle text-danger"></i>
    @endif
@endforeach
