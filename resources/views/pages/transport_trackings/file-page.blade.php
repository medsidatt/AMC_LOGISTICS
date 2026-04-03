<x-layouts.main :title="$documentTitle">
    <div class="card">
        <div class="card-body p-0">
            <iframe src="{{ $fileUrl }}" style="width:100%; height:85vh; border:0;" title="{{ $documentTitle }}"></iframe>
        </div>
    </div>
</x-layouts.main>
