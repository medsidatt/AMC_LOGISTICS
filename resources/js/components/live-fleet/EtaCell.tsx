interface Props {
    etaAt: string | null;
}

function relative(iso: string): string {
    const d = new Date(iso);
    const diffMin = Math.round((d.getTime() - Date.now()) / 60000);
    if (Math.abs(diffMin) < 1) return 'maintenant';
    if (diffMin > 0) {
        if (diffMin < 60) return `dans ${diffMin} min`;
        const h = Math.floor(diffMin / 60);
        const m = diffMin % 60;
        return m > 0 ? `dans ${h}h${m.toString().padStart(2, '0')}` : `dans ${h}h`;
    }
    const past = -diffMin;
    if (past < 60) return `il y a ${past} min`;
    const h = Math.floor(past / 60);
    return `il y a ${h}h`;
}

export default function EtaCell({ etaAt }: Props) {
    if (!etaAt) {
        return <span className="text-[var(--color-text-muted)] text-xs">—</span>;
    }

    const eta = new Date(etaAt);
    const isLate = eta.getTime() < Date.now();

    return (
        <div className="flex flex-col">
            <span className={isLate ? 'text-red-500 text-sm font-medium' : 'text-sm font-medium'}>
                {eta.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
            </span>
            <span className="text-xs text-[var(--color-text-muted)]">{relative(etaAt)}</span>
        </div>
    );
}
