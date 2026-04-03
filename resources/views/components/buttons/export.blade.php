@props([
    'text' => 'Export',
    'icon' => null,
    'type' => null,
])
<a href="{{ $href ?? '#' }}"
    {{ $attributes->merge(['class' => 'btn btn-sm btn-primary'])}}
>
    {{$text}} &nbsp;
    @if(isset($type) && $type == 'pdf')
        <span class="">
            <i class="bi bi-file-pdf"></i>
        </span>
    @elseif(isset($type) && $type == 'excel')
        <span class="">
            <i class="bi bi-file-excel"></i>
        </span>
    @endif
</a>
