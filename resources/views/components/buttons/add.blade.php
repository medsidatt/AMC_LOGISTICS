@props([
    'text' => __('global.add'),
])
<button type="button" {{ $attributes->merge(['class' => 'btn btn-sm btn-primary'])}} >
    <i class="bi bi-plus main-icon"></i> {{$text}}
    <span class="spinner-border-sm spinner-border indicator-progress d-none"
          role="status" aria-hidden="true"></span>
</button>
