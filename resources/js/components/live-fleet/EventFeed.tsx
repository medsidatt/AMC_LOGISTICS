import { clsx } from 'clsx';
import {
    Flag,
    Fuel,
    AlertTriangle,
    PauseOctagon,
    Route,
    MapPin,
    Truck as TruckIcon,
    PackageCheck,
    PackageOpen,
    LogIn,
    LogOut,
    WifiOff,
    Wifi,
    Wrench,
} from 'lucide-react';

export interface DispatchEvent {
    id: number;
    type: string;
    occurred_at: string | null;
    place?: { id: number; name: string; type: string } | null;
    payload?: Record<string, any> | null;
    latitude?: number | null;
    longitude?: number | null;
}

const ICONS: Record<string, { Icon: typeof Flag; color: string; label: string }> = {
    queued_at_quarry: { Icon: LogIn, color: 'text-blue-500', label: 'File à la carrière' },
    loading_started: { Icon: PackageOpen, color: 'text-amber-500', label: 'Chargement démarré' },
    loaded_and_left: { Icon: PackageCheck, color: 'text-emerald-500', label: 'Chargé & parti' },
    refuel: { Icon: Fuel, color: 'text-cyan-500', label: 'Plein de carburant' },
    fuel_loss: { Icon: AlertTriangle, color: 'text-red-500', label: 'Perte de carburant' },
    long_stop: { Icon: PauseOctagon, color: 'text-red-500', label: 'Arrêt long' },
    off_route: { Icon: Route, color: 'text-orange-500', label: 'Hors itinéraire' },
    border_crossed: { Icon: Flag, color: 'text-indigo-500', label: 'Passage frontière' },
    arrived_client: { Icon: MapPin, color: 'text-purple-500', label: 'Arrivé chez client' },
    unloaded: { Icon: PackageOpen, color: 'text-purple-600', label: 'Déchargé' },
    returning: { Icon: LogOut, color: 'text-emerald-500', label: 'Retour amorcé' },
    arrived_base: { Icon: TruckIcon, color: 'text-slate-500', label: 'Arrivé à la base' },
    offline: { Icon: WifiOff, color: 'text-gray-500', label: 'Hors ligne' },
    online: { Icon: Wifi, color: 'text-emerald-500', label: 'En ligne' },
    breakdown_suspected: { Icon: Wrench, color: 'text-red-500', label: 'Panne suspectée' },
};

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

export default function EventFeed({ events, compact = false }: { events: DispatchEvent[]; compact?: boolean }) {
    if (events.length === 0) {
        return <div className="text-sm text-[var(--color-text-muted)] py-3">Aucun événement pour le moment.</div>;
    }

    return (
        <ol className="relative border-l-2 border-[var(--color-border)] ml-2 space-y-3">
            {events.map((e) => {
                const meta = ICONS[e.type] ?? { Icon: Flag, color: 'text-gray-500', label: e.type };
                const { Icon } = meta;
                return (
                    <li key={e.id} className="ml-4">
                        <span className={clsx('absolute -left-[11px] flex h-5 w-5 items-center justify-center rounded-full bg-[var(--color-surface)] border-2 border-[var(--color-border)]', meta.color)}>
                            <Icon size={12} />
                        </span>
                        <div className={clsx('flex flex-col', compact ? 'gap-0' : 'gap-0.5')}>
                            <div className="flex items-baseline gap-2">
                                <span className={clsx('text-sm font-medium', meta.color)}>{meta.label}</span>
                                {e.place && (
                                    <span className="text-xs text-[var(--color-text-muted)]">— {e.place.name}</span>
                                )}
                            </div>
                            <div className="text-xs text-[var(--color-text-muted)]">
                                {formatTime(e.occurred_at)}
                                {e.payload?.duration_min && <span> · {e.payload.duration_min} min</span>}
                                {e.payload?.litres_delta !== undefined && (
                                    <span> · {e.payload.litres_delta > 0 ? '+' : ''}{Number(e.payload.litres_delta).toFixed(1)} L</span>
                                )}
                            </div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
