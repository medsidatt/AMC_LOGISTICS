@php
    $classes = [
        'en cours' => 'badge bg-primary',
        'active' => 'badge bg-success',
        'inactive' => 'badge bg-danger',
        'pending' => 'badge bg-warning',
        'completed' => 'badge bg-success',
        'canceled' => 'badge bg-danger',
        'approved' => 'badge bg-success',
        'rejected' => 'badge bg-danger',
        'answered' => 'badge bg-success',
        'unanswered' => 'badge bg-danger',
        'published' => 'badge bg-success',

        // Maintenance levels
        'red' => 'badge bg-danger',
        'yellow' => 'badge bg-warning',
        'green' => 'badge bg-success',
    ];

    $icons = [
        'active' => 'la la-check-circle',
        'inactive' => 'la la-times-circle',
        'pending' => 'la la-clock',
        'completed' => 'la la-check',
        'canceled' => 'la la-ban',
        'red' => 'la la-exclamation-triangle',
        'yellow' => 'la la-exclamation-circle',
        'green' => 'la la-check-circle',
    ];

    $labels = [
        'red' => __('A faire'),
        'yellow' => __('Attention'),
        'green' => __('OK'),
    ];
@endphp

<span class="{{ $classes[$status] ?? 'badge bg-secondary' }}" title="{{ $labels[$status] ?? ucfirst($status) }}">
    @if(isset($icons[$status]))
        <i class="{{ $icons[$status] }}"></i>
    @endif
    {{ $labels[$status] ?? ucfirst($status) }}
</span>
