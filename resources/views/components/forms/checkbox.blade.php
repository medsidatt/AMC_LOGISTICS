@props(['name', 'label', 'value' => 1, 'checked' => false, 'disabled' => false, 'required' => false])
{{--<div class="form-check">
    <input class="form-check-input" type="checkbox"
           value="{{ $value ?? 1 }}"
           id="{{ $name }}"
           name="{{ $name }}"
           @if(isset($checked) && $checked) checked @endif
           @if(isset($disabled) && $disabled) disabled @endif
    >
    <label class="form-check-label" for="defaultCheck1">
        {{ $label }}
        @if(isset($required) && $required)
            <span class="text-danger">*</span>
        @endif
    </label>
</div>--}}


<div class="custom-control custom-checkbox my-1 mr-sm-2">
    <input type="checkbox" class="custom-control-input"
           value="{{ $value ?? 1 }}"
           id="{{ $name }}"
           name="{{ $name }}"
           @if(isset($checked) && $checked) checked @endif
           @if(isset($disabled) && $disabled) disabled @endif
    >
    <label class="custom-control-label" for="{{ $name }}">
        {{ $label }}
        @if(isset($required) && $required)
            <span class="text-danger">*</span>
        @endif
    </label>
</div>
