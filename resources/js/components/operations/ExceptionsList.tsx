import Badge from '@/components/ui/Badge';
import { FileWarning, ClipboardCheck } from 'lucide-react';

export interface ExceptionItem {
    key: string;
    type: 'missing_ticket' | 'checklist_issue';
    severity: string;
    title: string;
    subtitle: string;
    at: string | null;
    link: string;
}

export function ExceptionRow({ item, padded }: { item: ExceptionItem; padded?: boolean }) {
    const isMissing = item.type === 'missing_ticket';
    return (
        <li className={padded ? 'px-4 py-3' : 'py-2.5'}>
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-start gap-2.5 min-w-0">
                    <span className={`mt-0.5 shrink-0 ${isMissing ? 'text-red-500' : 'text-amber-500'}`}>
                        {isMissing ? <FileWarning size={16} /> : <ClipboardCheck size={16} />}
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm font-medium truncate">{item.title}</p>
                        <p className="text-xs text-[var(--color-text-muted)] truncate">{item.subtitle}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <Badge variant={isMissing ? 'danger' : 'warning'}>{isMissing ? 'Manquant' : 'Checklist'}</Badge>
                    <a href={item.link} className="text-xs text-[var(--color-primary)] hover:underline whitespace-nowrap">
                        {isMissing ? 'Créer ticket' : 'Traiter'}
                    </a>
                </div>
            </div>
        </li>
    );
}

/** Shared exception worklist — used by the Operations Center feed and the Exceptions page. */
export default function ExceptionsList({ items, padded }: { items: ExceptionItem[]; padded?: boolean }) {
    return (
        <ul className="divide-y divide-[var(--color-border)]">
            {items.map((i) => <ExceptionRow key={i.key} item={i} padded={padded} />)}
        </ul>
    );
}
