import { useState, useRef, useEffect } from 'react';
import { ChevronDown, X, Search } from 'lucide-react';
import { clsx } from 'clsx';

interface Option {
    value: string | number;
    label: string;
}

interface SelectProps {
    options: Option[];
    value?: string | number | null;
    onChange: (value: string | number | null) => void;
    placeholder?: string;
    searchable?: boolean;
    clearable?: boolean;
    label?: string;
    className?: string;
}

export default function Select({
    options, value, onChange, placeholder = 'Sélectionner...', searchable = true, clearable = true, label, className,
}: SelectProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const selected = options.find((o) => o.value == value);
    const filtered = search
        ? options.filter((o) => o.label.toLowerCase().includes(search.toLowerCase()))
        : options;

    return (
        <div ref={ref} className={clsx('relative', className)}>
            {label && (
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">{label}</label>
            )}
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className={clsx(
                    'w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg border text-sm transition',
                    'bg-[var(--color-surface)] text-[var(--color-text)]',
                    open ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/20' : 'border-[var(--color-border)]',
                )}
            >
                <span className={clsx(!selected && 'text-[var(--color-text-muted)]')}>
                    {selected?.label ?? placeholder}
                </span>
                <span className="flex items-center gap-1">
                    {clearable && selected && (
                        <span
                            onClick={(e) => { e.stopPropagation(); onChange(null); setSearch(''); }}
                            className="p-0.5 rounded hover:bg-[var(--color-surface-hover)]"
                        >
                            <X size={14} />
                        </span>
                    )}
                    <ChevronDown size={16} className={clsx('transition', open && 'rotate-180')} />
                </span>
            </button>

            {open && (
                <div className="absolute z-50 mt-1 w-full bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-lg overflow-hidden">
                    {searchable && (
                        <div className="p-2 border-b border-[var(--color-border)]">
                            <div className="relative">
                                <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Rechercher..."
                                    className="w-full pl-8 pr-3 py-1.5 text-sm bg-transparent text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none"
                                    autoFocus
                                />
                            </div>
                        </div>
                    )}
                    <ul className="max-h-48 overflow-y-auto py-1">
                        {filtered.length === 0 ? (
                            <li className="px-3 py-2 text-sm text-[var(--color-text-muted)]">Aucun résultat</li>
                        ) : filtered.map((opt) => (
                            <li
                                key={opt.value}
                                onClick={() => { onChange(opt.value); setOpen(false); setSearch(''); }}
                                className={clsx(
                                    'px-3 py-2 text-sm cursor-pointer transition',
                                    opt.value == value
                                        ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium'
                                        : 'text-[var(--color-text)] hover:bg-[var(--color-surface-hover)]',
                                )}
                            >
                                {opt.label}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
