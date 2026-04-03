<x-layouts.main title="{{ __('Logistics Reports') }}">
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>{{ __('Issue frequency (last 30 days)') }}</strong>
            <span class="text-muted ms-2">({{ __('from') }}: {{ $from }})</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Open now') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issueFrequency as $row)
                        <tr>
                            <td class="fw-bold">{{ $row->category }}</td>
                            <td>{{ $row->total }}</td>
                            <td>{{ $row->open_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">{{ __('No data.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-secondary">
        {{ __('Total issues flagged in the period:') }} <strong>{{ $totalIssues }}</strong>
    </div>
</x-layouts.main>

