@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'type' => 'file',
    'accept' => 'image/*, application/pdf',
    'multiple' => false,
])
<div {{ $attributes->merge(['class' => 'form-group mb-2']) }}>
    @if(isset($label))
        <label for="{{ $name }}">{{ $label }}
            @if($required)
                <span class="text-danger" title="{{ __('Champ obligatoire') }}">*</span>
            @endif
        </label>
    @endif
    <input
        accept="{{ $accept }}"
        name="{{ $name }}"
        type="{{ $type }}"
        class="form-control @error($name) is-invalid @enderror"
        id="{{ $name }}"
        @if($multiple) multiple @endif
        @if($required) required aria-required="true" @endif
    >
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
