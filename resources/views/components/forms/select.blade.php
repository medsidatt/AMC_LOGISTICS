@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'options' => collect(),
    'value' => null,
    'dataPlaceholder' => __('global.select'),
    'id' => null,
    'onchange' => null,
    'valueField' => 'id',
    'labelField' => 'name',
    'secondLabelField' => null,
    'dataFilter' => null,
    'data-parent' => null,
    'selectClass' => 'form-control select2',
    'dataTags' => false,
    'dataFields' => [],
    'disabled' => false,
])

<div {{ $attributes->merge(['class' => 'form-group']) }}>
    @if(isset($label))
        <label for="{{ $id ?? $name }}">{{ $label }}
            @if($required)
                <span class="text-danger" title="{{ __('Champ obligatoire') }}">*</span>
            @endif
        </label>
    @endif
    <select
        onchange="{{ $onchange ?? null }}"
        class="{{ $selectClass }} @error($name) is-invalid @enderror"
        data-placeholder="{{ $dataPlaceholder }}"
        id="{{ $id ?? $name }}"
        name="{{ $name }}"
        @if($dataFilter) data-filter="{{ $dataFilter }}" @endif
        @if(isset($dataParent)) data-dropdown-parent="#{{ $dataParent }}" @endif
        @if($dataTags) data-tags="true" @endif
        @if($required) required aria-required="true" @endif
        @if($disabled) disabled @endif
        data-allow-clear="true"
    >
        <option></option>
        @if($options instanceof \Illuminate\Support\Collection && $options->isNotEmpty())
            @foreach($options as $option)
                <option
                    value="{{ $option[$valueField] }}"
                    {{ old($name, $value) == $option[$valueField] ? 'selected' : '' }}
                    @foreach($dataFields as $attr => $field)
                        data-{{ $attr }}="{{ $option[$field] ?? '' }}"
                    @endforeach
                >
                    {{ $option[$labelField] }}{{ $secondLabelField ? ' - ' . $option[$secondLabelField] : '' }}
                </option>
            @endforeach
        @endif
    </select>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
