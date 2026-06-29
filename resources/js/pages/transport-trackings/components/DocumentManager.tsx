import { useEffect, useMemo, useState } from 'react';
import Modal from '@/components/ui/Modal';
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import { FileText, X, Trash2, Eye, Loader2 } from 'lucide-react';

export interface TrackingDocument {
    id: number;
    original_name: string;
    mime_type: string;
    type: string;
    file_url: string;
}

interface Props {
    /** Already-saved documents. */
    existing?: TrackingDocument[];
    /** When provided, each existing doc shows a delete button. */
    onDeleteExisting?: (id: number) => void;
    deletingId?: number | null;
    /** Staged (not-yet-saved) files — form-drawer mode. */
    newFiles?: File[];
    onAddFiles?: (files: FileList) => void;
    onRemoveNew?: (index: number) => void;
    uploading?: boolean;
    addLabel?: string;
    emptyHint?: string;
}

const isPdfMime = (m: string) => m === 'application/pdf';

/**
 * Presentational document grid — existing documents (view / delete) + staged new
 * files (preview / remove) + a file picker, with a shared image-preview Modal.
 * Reused by the Transport form drawer (staged upload via the form) and the details
 * drawer's Documents tab (immediate upload). The single source of document UI.
 */
export default function DocumentManager({
    existing = [], onDeleteExisting, deletingId, newFiles = [], onAddFiles, onRemoveNew, uploading,
    addLabel = 'Ajouter des fichiers', emptyHint,
}: Props) {
    const [preview, setPreview] = useState<string | null>(null);

    // Object URLs for staged files, revoked when the file set changes/unmounts.
    const newPreviews = useMemo(
        () => newFiles.map((f) => (f.type.startsWith('image/') ? URL.createObjectURL(f) : '')),
        [newFiles],
    );
    useEffect(() => () => newPreviews.forEach((u) => u && URL.revokeObjectURL(u)), [newPreviews]);

    return (
        <div className="space-y-4">
            {onAddFiles && (
                <div>
                    <label className="block text-sm font-medium text-[var(--color-text)] mb-1">{addLabel}</label>
                    <input
                        type="file" multiple accept=".pdf,.jpg,.jpeg,.png" disabled={uploading}
                        onChange={(e) => { if (e.target.files) onAddFiles(e.target.files); e.target.value = ''; }}
                        className="block w-full text-sm text-[var(--color-text-secondary)] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[var(--color-primary)]/10 file:text-[var(--color-primary)] hover:file:bg-[var(--color-primary)]/20 disabled:opacity-50"
                    />
                    {uploading && (
                        <p className="mt-2 text-xs text-[var(--color-text-muted)] inline-flex items-center gap-1">
                            <Loader2 size={12} className="animate-spin" /> Téléversement…
                        </p>
                    )}
                </div>
            )}

            {newFiles.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {newFiles.map((file, i) => {
                        const isImage = file.type.startsWith('image/');
                        const url = newPreviews[i];
                        return (
                            <div key={i} className="relative group rounded-lg border border-[var(--color-border)] overflow-hidden bg-[var(--color-surface-hover)]">
                                {isImage && url ? (
                                    <img src={url} alt={file.name} className="w-full h-28 object-cover cursor-pointer" onClick={() => setPreview(url)} />
                                ) : (
                                    <div className="w-full h-28 flex flex-col items-center justify-center"><FileText size={32} className="text-red-400" /><span className="text-xs text-[var(--color-text-muted)] mt-1">PDF</span></div>
                                )}
                                <div className="px-2 py-1.5">
                                    <p className="text-xs text-[var(--color-text)] truncate">{file.name}</p>
                                    <p className="text-xs text-[var(--color-text-muted)]">{(file.size / 1024).toFixed(0)} KB</p>
                                </div>
                                {onRemoveNew && (
                                    <button type="button" onClick={() => onRemoveNew(i)} className="absolute top-1 right-1 p-1 rounded-full bg-red-500 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                                        <X size={12} />
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {existing.length > 0 ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {existing.map((doc) => (
                        <div key={doc.id} className="relative group rounded-lg border border-[var(--color-border)] overflow-hidden bg-[var(--color-surface-hover)]">
                            {!isPdfMime(doc.mime_type) ? (
                                <img src={doc.file_url} alt={doc.original_name} className="w-full h-28 object-cover cursor-pointer" onClick={() => setPreview(doc.file_url)} />
                            ) : (
                                <a href={doc.file_url} target="_blank" rel="noreferrer" className="w-full h-28 flex flex-col items-center justify-center hover:bg-[var(--color-surface)]"><FileText size={32} className="text-red-400" /><span className="text-xs text-[var(--color-text-muted)] mt-1">PDF</span></a>
                            )}
                            <div className="px-2 py-1.5 flex items-center justify-between gap-1">
                                <div className="min-w-0">
                                    <p className="text-xs text-[var(--color-text)] truncate">{doc.original_name}</p>
                                    <Badge variant="muted">{doc.type}</Badge>
                                </div>
                                <div className="flex items-center gap-0.5 shrink-0">
                                    <a href={doc.file_url} target="_blank" rel="noreferrer" className="p-1 text-[var(--color-info)] hover:bg-[var(--color-info)]/10 rounded" title="Ouvrir"><Eye size={14} /></a>
                                    {onDeleteExisting && (
                                        <button type="button" onClick={() => onDeleteExisting(doc.id)} disabled={deletingId === doc.id} className="p-1 text-red-500 hover:bg-red-500/10 rounded disabled:opacity-40" title="Supprimer">
                                            {deletingId === doc.id ? <Loader2 size={14} className="animate-spin" /> : <Trash2 size={14} />}
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                newFiles.length === 0 && <EmptyState icon={<FileText size={28} />} title="Aucun document" description={emptyHint} />
            )}

            <Modal open={!!preview} onClose={() => setPreview(null)} size="xl">
                {preview && <img src={preview} alt="Aperçu" className="w-full h-auto rounded-lg" />}
            </Modal>
        </div>
    );
}
