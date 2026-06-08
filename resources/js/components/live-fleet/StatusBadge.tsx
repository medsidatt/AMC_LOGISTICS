import { clsx } from 'clsx';

export type LiveStatus =
    | 'FILE_CARRIERE'
    | 'CHARGEMENT'
    | 'EN_ROUTE'
    | 'RETOUR'
    | 'CHEZ_CLIENT'
    | 'RAVITAILLEMENT'
    | 'PASSAGE_FRONTIERE'
    | 'A_LA_BASE'
    | 'ARRET_LONG'
    | 'ARRET'
    | 'OFFLINE'
    | 'TERMINE'
    | null
    | undefined;

const LABELS: Record<string, string> = {
    FILE_CARRIERE: 'File carrière',
    CHARGEMENT: 'Chargement',
    EN_ROUTE: 'En route',
    RETOUR: 'Retour',
    CHEZ_CLIENT: 'Chez client',
    RAVITAILLEMENT: 'Ravitaillement',
    PASSAGE_FRONTIERE: 'Passage frontière',
    A_LA_BASE: 'À la base',
    ARRET_LONG: 'Arrêt long',
    ARRET: 'À l\'arrêt',
    OFFLINE: 'Hors ligne',
    TERMINE: 'Terminé',
};

const COLORS: Record<string, string> = {
    FILE_CARRIERE: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
    CHARGEMENT: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    EN_ROUTE: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    RETOUR: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    CHEZ_CLIENT: 'bg-purple-500/15 text-purple-600 dark:text-purple-400',
    RAVITAILLEMENT: 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
    PASSAGE_FRONTIERE: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
    A_LA_BASE: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
    ARRET_LONG: 'bg-red-500/15 text-red-600 dark:text-red-400',
    ARRET: 'bg-zinc-500/15 text-zinc-600 dark:text-zinc-300',
    OFFLINE: 'bg-gray-500/15 text-gray-500 dark:text-gray-400',
    TERMINE: 'bg-slate-500/10 text-slate-500 dark:text-slate-400',
};

interface Props {
    status: LiveStatus;
    size?: 'sm' | 'md';
}

export function statusLabel(status: LiveStatus): string {
    if (!status) return '—';
    return LABELS[status] ?? status;
}

export default function StatusBadge({ status, size = 'sm' }: Props) {
    if (!status) {
        return <span className="text-[var(--color-text-muted)] text-xs">—</span>;
    }
    return (
        <span
            className={clsx(
                'inline-flex items-center font-medium rounded-full',
                COLORS[status] ?? 'bg-gray-500/10 text-gray-600',
                size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm',
            )}
        >
            {LABELS[status] ?? status}
        </span>
    );
}
