import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { clsx } from 'clsx';

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PaginationProps {
    meta: PaginationMeta;
    preserveState?: boolean;
}

export default function Pagination({ meta, preserveState = true }: PaginationProps) {
    if (meta.last_page <= 1) return null;

    const navigate = (page: number) => {
        router.get(window.location.pathname, { ...Object.fromEntries(new URLSearchParams(window.location.search)), page }, {
            preserveState,
            preserveScroll: true,
        });
    };

    const pages: number[] = [];
    const range = 2;
    for (let i = Math.max(1, meta.current_page - range); i <= Math.min(meta.last_page, meta.current_page + range); i++) {
        pages.push(i);
    }

    return (
        <div className="flex items-center justify-between mt-4 text-sm">
            <span className="text-[var(--color-text-muted)]">
                {meta.from}–{meta.to} sur {meta.total}
            </span>
            <div className="flex items-center gap-1">
                <button
                    onClick={() => navigate(meta.current_page - 1)}
                    disabled={meta.current_page === 1}
                    className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] disabled:opacity-30"
                >
                    <ChevronLeft size={16} />
                </button>
                {pages[0] > 1 && (
                    <>
                        <button onClick={() => navigate(1)} className="w-8 h-8 rounded-lg text-sm hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">1</button>
                        {pages[0] > 2 && <span className="text-[var(--color-text-muted)]">...</span>}
                    </>
                )}
                {pages.map((p) => (
                    <button
                        key={p}
                        onClick={() => navigate(p)}
                        className={clsx(
                            'w-8 h-8 rounded-lg text-sm font-medium transition',
                            p === meta.current_page
                                ? 'bg-[var(--color-primary)] text-white'
                                : 'hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
                        )}
                    >
                        {p}
                    </button>
                ))}
                {pages[pages.length - 1] < meta.last_page && (
                    <>
                        {pages[pages.length - 1] < meta.last_page - 1 && <span className="text-[var(--color-text-muted)]">...</span>}
                        <button onClick={() => navigate(meta.last_page)} className="w-8 h-8 rounded-lg text-sm hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">{meta.last_page}</button>
                    </>
                )}
                <button
                    onClick={() => navigate(meta.current_page + 1)}
                    disabled={meta.current_page === meta.last_page}
                    className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] disabled:opacity-30"
                >
                    <ChevronRight size={16} />
                </button>
            </div>
        </div>
    );
}
