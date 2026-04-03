<div {{ $attributes->merge(['class' => 'input-group mb-3']) }}>
    @if(isset($label))
        <span id="{{ $name }}" class="input-group-text">{{ $label }}
            @if(isset($required) && $required)
                <span class="text-danger" data-bs-toggle="tooltip" data-bs-placement="top"
                      title="field is required">*</span>
            @endif
        </span>
    @endif
    <input type="{{ $type ?? 'text' }}"
           {{ $attributes->merge(['class' => 'form-control'])}}
           id="{{ $name }}"
           aria-describedby="{{ $name }}"
    ></div>
