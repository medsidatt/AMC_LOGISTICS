@if($user->is_used == "0")
    <span class="badge badge-success">
        Pending invitation
    </span>
    <span class="badge badge-danger">
        Expire {{ Carbon\Carbon::parse($user->expires_at)->diffForHumans() }}
    </span>
@endif
