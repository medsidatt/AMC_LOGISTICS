<div class="col-md-12 col-lg-3 text-center mb-2">
    <h6 class="text-muted">
        {{ __($title) }}
    </h6>
    <h2 class="block font-weight-normal">
        {{ $value }} @if($unit) {{ $unit }} @endif
    </h2>

    @if($percentage !== null)
        <div class="progress progress-sm mt-1 mb-0 box-shadow-2">
            <div class="progress-bar bg-gradient-x-{{ $color }}" role="progressbar"
                 style="width: {{ $percentage }}%"
                 aria-valuenow="{{ $percentage }}"
                 aria-valuemin="0" aria-valuemax="100">
            </div>
        </div>
    @endif
</div>
