@props([
    'text' => __('global.save'),
    'type' => 'button',
    'icon' => 'bi bi-save',
])
<div class="d-flex justify-content-end mt-2">
    <button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-success']) }}>
        <i class="{{ $icon }} main-icon"></i>
        <span class="indicator-progress d-none">
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
        </span>
        {{ $text }}
        <i class="bi bi-check well-saved d-none"></i>
    </button>
</div>
