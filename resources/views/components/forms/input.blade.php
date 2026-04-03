@props([
    'name' => null,
    'label' => null,
    'placeholder' => null,
    'required' => false,
    'type' => 'text',
    'value' => null,
    'dataFilter' => null,
    'disabled' => false,
    'readonly' => false,
    'min' => null,
    'max' => null,
    'step' => null,
    'helpText' => null,
])
<div {{ $attributes->merge(['class' => 'form-group']) }}>
    @if(isset($label))
        <label for="{{ $name }}">{{ $label }}
            @if($required)
                <span class="text-danger" title="{{ __('Champ obligatoire') }}">*</span>
            @endif
        </label>
    @endif
    <input type="{{ $type }}"
           class="form-control @error($name) is-invalid @enderror"
           id="{{ $name }}"
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           placeholder="{{ $placeholder ?? '' }}"
           @if($dataFilter) data-filter="{{ $dataFilter }}" @endif
           @if($required) required aria-required="true" @endif
           @if($disabled) disabled @endif
           @if($readonly) readonly @endif
           @if($min !== null) min="{{ $min }}" @endif
           @if($max !== null) max="{{ $max }}" @endif
           @if($step !== null) step="{{ $step }}" @endif
    >
    @if($helpText)
        <small class="form-text text-muted">{{ $helpText }}</small>
    @endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
