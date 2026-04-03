@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'value' => null,
    'placeholder' => null,
    'rows' => 3,
    'disabled' => false,
    'readonly' => false,
])
<div {{ $attributes->merge(['class' => 'form-group mb-3']) }}>
    @if(isset($label))
        <label for="{{ $name }}">{{ $label }}
            @if($required)
                <span class="text-danger" title="{{ __('Champ obligatoire') }}">*</span>
            @endif
        </label>
    @endif
    <textarea
        name="{{ $name }}"
        class="form-control @error($name) is-invalid @enderror"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required aria-required="true" @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
    >{{ old($name, $value) }}</textarea>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
