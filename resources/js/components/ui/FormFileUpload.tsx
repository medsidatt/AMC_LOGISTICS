import { useRef, useState, type DragEvent } from 'react';
import { Upload, X, FileText } from 'lucide-react';
import { clsx } from 'clsx';

interface FormFileUploadProps {
    label?: string;
    accept?: string;
    multiple?: boolean;
    onChange: (files: File[]) => void;
    error?: string;
    value?: File[];
    wrapperClass?: string;
}

export default function FormFileUpload({ label, accept, multiple, onChange, error, value = [], wrapperClass }: FormFileUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    const handleFiles = (fileList: FileList | null) => {
        if (!fileList) return;
        const files = Array.from(fileList);
        onChange(multiple ? [...value, ...files] : files);
    };

    const handleDrop = (e: DragEvent) => {
        e.preventDefault();
        setDragging(false);
        handleFiles(e.dataTransfer.files);
    };

    const removeFile = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
    };

    return (
        <div className={clsx('mb-4', wrapperClass)}>
            {label && (
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">{label}</label>
            )}
            <div
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={handleDrop}
                onClick={() => inputRef.current?.click()}
                className={clsx(
                    'border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors',
                    dragging ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)] hover:border-[var(--color-primary)]/50',
                    error && 'border-[var(--color-danger)]',
                )}
            >
                <Upload size={24} className="mx-auto text-[var(--color-text-muted)] mb-2" />
                <p className="text-sm text-[var(--color-text-secondary)]">
                    Glisser-déposer ou <span className="text-[var(--color-primary)] font-medium">parcourir</span>
                </p>
                {accept && <p className="text-xs text-[var(--color-text-muted)] mt-1">{accept}</p>}
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    onChange={(e) => handleFiles(e.target.files)}
                    className="hidden"
                />
            </div>

            {value.length > 0 && (
                <div className="mt-2 space-y-1">
                    {value.map((file, i) => (
                        <div key={i} className="flex items-center gap-2 p-2 rounded-lg bg-[var(--color-surface-hover)]">
                            <FileText size={14} className="text-[var(--color-text-muted)]" />
                            <span className="text-sm text-[var(--color-text)] truncate flex-1">{file.name}</span>
                            <button onClick={(e) => { e.stopPropagation(); removeFile(i); }} className="p-1 rounded hover:bg-[var(--color-surface)]">
                                <X size={12} />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
