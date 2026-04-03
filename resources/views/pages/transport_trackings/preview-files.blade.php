<div class="p-2">
    @if($documents->count() > 0)
        @foreach($documents as $document)
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    @if($document->type === 'provider')
                        <i class="fas fa-truck-loading text-primary"></i> Fournisseur
                    @elseif($document->type === 'client')
                        <i class="fas fa-user-tag text-warning"></i> Client
                    @elseif($document->type === 'commune')
                        <i class="fas fa-building text-info"></i> Commune
                    @else
                        <i class="fas fa-file text-secondary"></i> Autre
                    @endif
                    &mdash; {{ $document->original_name ?? basename($document->file_path) }}
                </h6>

                @if(\Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path))
                    @if($document->mime_type === 'application/pdf')
                        <iframe
                            src="{{ \Illuminate\Support\Facades\Storage::url($document->file_path) }}"
                            style="width: 100%; height: 70vh; border: 1px solid #dee2e6; border-radius: 4px;"
                            frameborder="0">
                        </iframe>
                    @elseif(in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/jpg']))
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::url($document->file_path) }}"
                            class="img-fluid rounded shadow-sm"
                            style="max-height: 70vh; width: 100%; object-fit: contain;"
                            alt="{{ $document->original_name }}">
                    @endif
                @else
                    <div class="alert alert-warning py-2">
                        <i class="fas fa-exclamation-triangle"></i> Fichier introuvable sur le serveur.
                    </div>
                @endif
            </div>

            @if(!$loop->last)
                <hr>
            @endif
        @endforeach
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Aucun fichier associé à ce transport tracking.
        </div>
    @endif
</div>
