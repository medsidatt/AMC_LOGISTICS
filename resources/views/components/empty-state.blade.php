@props([
    'icon' => 'la la-inbox',
    'title' => null,
    'message' => null,
    'actionUrl' => null,
    'actionLabel' => null,
])
<div class="text-center py-4">
    <i class="{{ $icon }} text-muted" style="font-size: 3rem;"></i>
    @if($title)
        <h6 class="mt-2 text-muted">{{ $title }}</h6>
    @endif
    @if($message)
        <p class="text-muted mb-2">{{ $message }}</p>
    @endif
    @if($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="btn btn-sm btn-primary mt-1">
            <i class="la la-plus mr-1"></i>{{ $actionLabel }}
        </a>
    @endif
</div>
