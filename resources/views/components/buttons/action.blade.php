@props([
    'actions' => [],
    'href' => '#',
    'onclick' => null,
    'label' => null,
    'permission' => true,
    'target' => null,
])

@php
    $filteredActions = array_filter($actions, function ($action) {
        return (is_bool($action['permission']) && $action['permission']) || (\Illuminate\Support\Facades\Gate::allows($action['permission']));
    });
@endphp

@if(!empty($filteredActions))
    <div class="btn-group btn-group-sm float-md-right"
         role="group"
         aria-label="{{ __('Actions') }}"
    >
        <button
            class="btn btn-info dropdown-toggle dropdown-menu-right box-shadow-2 mb-1"
            id="btnGroupDrop" type="button"
            data-toggle="dropdown"
            aria-haspopup="true"
            aria-expanded="false"
            aria-label="{{ $label ?? __('Actions') }}"
        >
            {{ $label ?? __('Actions') }}
        </button>

        <div class="dropdown-menu" aria-labelledby="btnGroupDrop">
            @foreach($filteredActions as $action)
                @if(isset($action['onclick']))
                    <button
                        type="button"
                        onclick="{{ $action['onclick'] }}"
                        class="dropdown-item"
                    >
                        {{ $action['label'] ?? '' }}
                    </button>
                @else
                    <a
                        {{ isset($action['target']) ? 'target=' . $action['target'] : '' }}
                        class="dropdown-item"
                        href="{{ $action['href'] ?? $action['url'] ?? '#' }}"
                    >
                        {{ $action['label'] ?? '' }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
