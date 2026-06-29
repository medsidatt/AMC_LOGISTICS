import { useEffect, useRef, useState } from 'react';
import { apiFetch } from '@/utils/csrf';
import ProductDrawer, { type CatalogProduct } from './ProductDrawer';
import { Search, Plus, Check, Loader2 } from 'lucide-react';

interface Props {
    value: number | null;
    /** Current product name (for display when a value is already set, e.g. on edit). */
    displayName?: string | null;
    onChange: (productId: number | null, product?: CatalogProduct) => void;
    categories?: { value: string; label: string }[];
    units?: { value: string; label: string }[];
    placeholder?: string;
    error?: string;
}

/**
 * Shared, searchable Product Catalog selector — active-only, ordered, loaded from
 * the DB (never hardcoded). When a product isn't found, "+ Ajouter" opens the
 * shared ProductDrawer; on save it refreshes the selection and auto-selects the
 * new product while the parent form stays open. The only product dropdown in the app.
 */
export default function ProductSelector({ value, displayName, onChange, categories, units, placeholder = 'Rechercher un produit…', error }: Props) {
    const [query, setQuery] = useState(displayName ?? '');
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<CatalogProduct[]>([]);
    const [loading, setLoading] = useState(false);
    const [showCreate, setShowCreate] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);

    useEffect(() => { setQuery(displayName ?? ''); }, [displayName]);

    useEffect(() => {
        if (!open) return;
        const t = setTimeout(async () => {
            setLoading(true);
            try {
                const res = await apiFetch(`/products?search=${encodeURIComponent(query)}`);
                if (res.ok) { const j = await res.json(); setOptions(j.products ?? []); }
            } finally {
                setLoading(false);
            }
        }, 250);
        return () => clearTimeout(t);
    }, [query, open]);

    useEffect(() => {
        const onDoc = (e: MouseEvent) => { if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false); };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    const select = (p: CatalogProduct) => { onChange(p.id, p); setQuery(p.name); setOpen(false); };
    const exactMatch = options.some((o) => o.name.toLowerCase() === query.trim().toLowerCase());
    const canCreate = query.trim() !== '' && !exactMatch;

    return (
        <div className="relative" ref={boxRef}>
            <div className="relative">
                <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                <input
                    value={query}
                    onChange={(e) => { setQuery(e.target.value); setOpen(true); if (value != null) onChange(null); }}
                    onFocus={() => setOpen(true)}
                    placeholder={placeholder}
                    className={`w-full pl-8 pr-7 py-2 rounded-lg border bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] ${error ? 'border-[var(--color-danger)]' : 'border-[var(--color-border)]'}`}
                />
                {value != null && <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-success)]"><Check size={14} /></span>}
            </div>
            {error && <p className="mt-0.5 text-xs text-[var(--color-danger)]">{error}</p>}

            {open && (
                <div className="absolute z-20 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg">
                    {loading && <div className="px-3 py-2 text-xs text-[var(--color-text-muted)] inline-flex items-center gap-1"><Loader2 size={12} className="animate-spin" /> Recherche…</div>}
                    {!loading && options.map((o) => (
                        <button key={o.id} type="button" onClick={() => select(o)} className="w-full text-left px-3 py-2 text-sm hover:bg-[var(--color-surface-hover)] flex items-center justify-between gap-2">
                            <span className="min-w-0 truncate">{o.name}{o.reference ? <span className="text-xs text-[var(--color-text-muted)]"> · {o.reference}</span> : null}</span>
                            {value === o.id && <Check size={14} className="text-[var(--color-success)] shrink-0" />}
                        </button>
                    ))}
                    {!loading && options.length === 0 && query.trim() === '' && <div className="px-3 py-2 text-xs text-[var(--color-text-muted)]">Tapez pour rechercher un produit…</div>}
                    {!loading && canCreate && (
                        <button type="button" onClick={() => setShowCreate(true)} className="w-full text-left px-3 py-2 text-sm text-[var(--color-primary)] hover:bg-[var(--color-surface-hover)] border-t border-[var(--color-border)] inline-flex items-center gap-1">
                            <Plus size={14} /> Ajouter « {query.trim()} »
                        </button>
                    )}
                </div>
            )}

            {showCreate && (
                <ProductDrawer
                    initialName={query.trim()}
                    categories={categories}
                    units={units}
                    onCreated={(p) => { setShowCreate(false); select(p); }}
                    onClose={() => setShowCreate(false)}
                />
            )}
        </div>
    );
}
