import { useState, useMemo } from 'react';
import { ChevronUp, ChevronDown, ChevronsUpDown, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { clsx } from 'clsx';

interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    render?: (row: T) => React.ReactNode;
    className?: string;
    hideOnMobile?: boolean;
}

interface DataTableProps<T> {
    data: T[];
    columns: Column<T>[];
    perPage?: number;
    searchable?: boolean;
    searchKeys?: string[];
    emptyMessage?: string;
    mobileCard?: (row: T) => React.ReactNode;
}

type SortDir = 'asc' | 'desc' | null;

export default function DataTable<T extends Record<string, any>>({
    data, columns, perPage = 10, searchable = true, searchKeys, emptyMessage = 'Aucune donnée', mobileCard,
}: DataTableProps<T>) {
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDir, setSortDir] = useState<SortDir>(null);
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        let result = [...data];
        if (search) {
            const q = search.toLowerCase();
            const keys = searchKeys ?? columns.map((c) => c.key);
            result = result.filter((row) =>
                keys.some((k) => String(row[k] ?? '').toLowerCase().includes(q)),
            );
        }
        if (sortKey && sortDir) {
            result.sort((a, b) => {
                const av = a[sortKey] ?? '';
                const bv = b[sortKey] ?? '';
                const cmp = typeof av === 'number' ? av - (bv as number) : String(av).localeCompare(String(bv));
                return sortDir === 'desc' ? -cmp : cmp;
            });
        }
        return result;
    }, [data, search, sortKey, sortDir, searchKeys, columns]);

    const totalPages = Math.ceil(filtered.length / perPage);
    const paged = filtered.slice((page - 1) * perPage, page * perPage);

    const handleSort = (key: string) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : sortDir === 'desc' ? null : 'asc');
            if (sortDir === 'desc') setSortKey(null);
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
        setPage(1);
    };

    const SortIcon = ({ col }: { col: string }) => {
        if (sortKey !== col) return <ChevronsUpDown size={14} className="opacity-30" />;
        if (sortDir === 'asc') return <ChevronUp size={14} />;
        return <ChevronDown size={14} />;
    };

    return (
        <div>
            {searchable && (
                <div className="mb-4 relative">
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        placeholder="Rechercher..."
                        className="w-full sm:w-72 pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                    />
                </div>
            )}

            {/* Desktop table */}
            <div className="hidden md:block overflow-x-auto rounded-lg border border-[var(--color-border)]">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-[var(--color-surface-hover)]">
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    className={clsx(
                                        'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)]',
                                        col.sortable !== false && 'cursor-pointer select-none hover:text-[var(--color-text)]',
                                        col.className,
                                    )}
                                    onClick={() => col.sortable !== false && handleSort(col.key)}
                                >
                                    <span className="inline-flex items-center gap-1">
                                        {col.label}
                                        {col.sortable !== false && <SortIcon col={col.key} />}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--color-border)]">
                        {paged.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : paged.map((row, i) => (
                            <tr key={i} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                {columns.map((col) => (
                                    <td key={col.key} className={clsx('px-4 py-3 text-[var(--color-text)]', col.className)}>
                                        {col.render ? col.render(row) : String(row[col.key] ?? '-')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile cards */}
            <div className="md:hidden space-y-3">
                {paged.length === 0 ? (
                    <p className="text-center py-8 text-[var(--color-text-muted)]">{emptyMessage}</p>
                ) : paged.map((row, i) => (
                    mobileCard ? (
                        <div key={i}>{mobileCard(row)}</div>
                    ) : (
                        <div key={i} className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 space-y-2">
                            {columns.filter((c) => !c.hideOnMobile).map((col) => (
                                <div key={col.key} className="flex justify-between items-start gap-2">
                                    <span className="text-xs font-medium text-[var(--color-text-muted)] uppercase">{col.label}</span>
                                    <span className="text-sm text-[var(--color-text)] text-right">
                                        {col.render ? col.render(row) : String(row[col.key] ?? '-')}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )
                ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
                <div className="flex items-center justify-between mt-4 text-sm">
                    <span className="text-[var(--color-text-muted)]">
                        {(page - 1) * perPage + 1}–{Math.min(page * perPage, filtered.length)} sur {filtered.length}
                    </span>
                    <div className="flex items-center gap-1">
                        <button
                            onClick={() => setPage(Math.max(1, page - 1))}
                            disabled={page === 1}
                            className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] disabled:opacity-30 text-[var(--color-text-secondary)]"
                        >
                            <ChevronLeft size={16} />
                        </button>
                        {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                            const p = page <= 3 ? i + 1 : page + i - 2;
                            if (p < 1 || p > totalPages) return null;
                            return (
                                <button
                                    key={p}
                                    onClick={() => setPage(p)}
                                    className={clsx(
                                        'w-8 h-8 rounded-lg text-sm font-medium transition',
                                        p === page
                                            ? 'bg-[var(--color-primary)] text-white'
                                            : 'hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
                                    )}
                                >
                                    {p}
                                </button>
                            );
                        })}
                        <button
                            onClick={() => setPage(Math.min(totalPages, page + 1))}
                            disabled={page === totalPages}
                            className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] disabled:opacity-30 text-[var(--color-text-secondary)]"
                        >
                            <ChevronRight size={16} />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
