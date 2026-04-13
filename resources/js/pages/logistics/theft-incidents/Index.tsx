import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import LeafletMap from '@/components/map/LeafletMap';
import {
    AlertTriangle, Shield, ShieldCheck, ShieldAlert, ShieldX, ShieldOff,
    Fuel, Scale, StopCircle, Navigation, Clock, Eye, MapPin,
    Filter, ChevronDown, ChevronUp, Truck as TruckIcon, Siren,
} from 'lucide-react';

/* ─────────────────────────── Types ─────────────────────────── */

interface Incident {
    id: number;
    type: string;
    severity: string;
    status: string;
    title: string;
    detected_at: string | null;
    detected_at_raw: string | null;
    latitude: number | null;
    longitude: number | null;
    truck: { id: number; matricule: string } | null;
    transport_tracking: { id: number; reference: string } | null;
    reviewer: string | null;
    reviewed_at: string | null;
}

interface TruckOption { id: number; matricule: string; }

interface Filters {
    type?: string;
    severity?: string;
    status?: string;
    truck_id?: string;
    from?: string;
    to?: string;
}

interface Stats {
    pending: number;
    confirmed: number;
    dismissed: number;
    reviewed: number;
    high: number;
    last_7_days: number;
    last_24h: number;
    total: number;
    by_type: Record<string, number>;
}

interface Props {
    incidents: Incident[];
    filters: Filters;
    stats: Stats;
    trucks: TruckOption[];
}

/* ─────────────────────────── Constants ─────────────────────── */

const TYPE_CONFIG: Record<string, { label: string; icon: typeof Fuel; color: string; bg: string }> = {
    fuel_siphoning:     { label: 'Vol de carburant',    icon: Fuel,        color: 'text-red-600',    bg: 'bg-red-100 dark:bg-red-900/30' },
    weight_gap:         { label: 'Écart de poids',      icon: Scale,       color: 'text-orange-600', bg: 'bg-orange-100 dark:bg-orange-900/30' },
    unauthorized_stop:  { label: 'Arrêt non autorisé',  icon: StopCircle,  color: 'text-amber-600',  bg: 'bg-amber-100 dark:bg-amber-900/30' },
    route_deviation:    { label: "Déviation d'itinéraire", icon: Navigation, color: 'text-purple-600', bg: 'bg-purple-100 dark:bg-purple-900/30' },
    off_hours_movement: { label: 'Mouvement hors horaires', icon: Clock,   color: 'text-blue-600',   bg: 'bg-blue-100 dark:bg-blue-900/30' },
};

const SEVERITY_MAP: Record<string, { label: string; variant: 'danger' | 'warning' | 'info'; dot: string }> = {
    high:   { label: 'Haute',   variant: 'danger',  dot: 'bg-red-500' },
    medium: { label: 'Moyenne', variant: 'warning', dot: 'bg-amber-500' },
    low:    { label: 'Basse',   variant: 'info',    dot: 'bg-blue-500' },
};

const STATUS_MAP: Record<string, { label: string; variant: 'warning' | 'info' | 'danger' | 'muted' | 'success' }> = {
    pending:   { label: 'En attente', variant: 'warning' },
    reviewed:  { label: 'Examiné',    variant: 'info' },
    confirmed: { label: 'Confirmé',   variant: 'danger' },
    dismissed: { label: 'Rejeté',     variant: 'muted' },
};

const STATUS_TABS = [
    { key: '',          label: 'Tous',       icon: Shield },
    { key: 'pending',   label: 'En attente', icon: ShieldAlert },
    { key: 'confirmed', label: 'Confirmés',  icon: ShieldX },
    { key: 'reviewed',  label: 'Examinés',   icon: ShieldCheck },
    { key: 'dismissed', label: 'Rejetés',    icon: ShieldOff },
] as const;

const INCIDENT_MAP_COLOR: Record<string, string> = {
    fuel_siphoning: '#ef4444',
    weight_gap: '#f97316',
    unauthorized_stop: '#f59e0b',
    route_deviation: '#a855f7',
    off_hours_movement: '#3b82f6',
};

/* ─────────────────────────── Page ──────────────────────────── */

export default function TheftIncidentsIndex({ incidents, filters, stats, trucks }: Props) {
    const [filtersOpen, setFiltersOpen] = useState(
        Boolean(filters.type || filters.severity || filters.truck_id || filters.from || filters.to)
    );
    const [formFilters, setFormFilters] = useState<Filters>({
        type: filters.type ?? '',
        severity: filters.severity ?? '',
        status: filters.status ?? '',
        truck_id: filters.truck_id ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const activeTab = formFilters.status ?? '';

    const applyFilters = (overrides?: Partial<Filters>) => {
        const merged = { ...formFilters, ...overrides };
        const clean: Record<string, string> = {};
        Object.entries(merged).forEach(([k, v]) => {
            if (v && v !== '') clean[k] = String(v);
        });
        router.get('/logistics/theft-incidents', clean, { preserveState: true });
    };

    const switchTab = (status: string) => {
        const next = { ...formFilters, status };
        setFormFilters(next);
        applyFilters(next);
    };

    const resetFilters = () => {
        setFormFilters({ type: '', severity: '', status: '', truck_id: '', from: '', to: '' });
        router.get('/logistics/theft-incidents', {}, { preserveState: true });
    };

    /* Map markers for incidents with GPS */
    const mapMarkers = useMemo(
        () => incidents
            .filter((i) => i.latitude !== null && i.longitude !== null)
            .map((i) => ({
                id: i.id,
                latitude: i.latitude!,
                longitude: i.longitude!,
                color: INCIDENT_MAP_COLOR[i.type] ?? '#ef4444',
                popup: (
                    <div style={{ minWidth: 200 }}>
                        <strong>{i.title}</strong>
                        <div style={{ fontSize: 12, marginTop: 4, color: '#6b7280' }}>
                            {i.truck?.matricule ?? '-'} &mdash; {i.detected_at}
                        </div>
                        <a href={`/logistics/theft-incidents/${i.id}`} style={{ fontSize: 12, color: '#2563eb', marginTop: 4, display: 'inline-block' }}>
                            Voir les détails →
                        </a>
                    </div>
                ),
            })),
        [incidents]
    );

    /* Type breakdown pills */
    const typeBreakdown = Object.entries(TYPE_CONFIG)
        .map(([key, cfg]) => ({
            key,
            ...cfg,
            count: stats.by_type[key] ?? 0,
        }))
        .sort((a, b) => b.count - a.count);

    const hasAnyIncident = stats.total > 0;
    const hasGeoIncidents = mapMarkers.length > 0;

    return (
        <AuthenticatedLayout title="Incidents de vol">
            <Head title="Incidents de vol" />

            {/* ── Alert banner when there are high-severity pending incidents ── */}
            {stats.high > 0 && (
                <div className="mb-5 flex items-center gap-3 rounded-xl border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-5 py-4">
                    <div className="p-2 rounded-lg bg-red-100 dark:bg-red-900/40">
                        <Siren size={22} className="text-red-600" />
                    </div>
                    <div className="flex-1">
                        <div className="font-semibold text-red-800 dark:text-red-300">
                            {stats.high} incident{stats.high > 1 ? 's' : ''} de haute sévérité en attente
                        </div>
                        <div className="text-sm text-red-600 dark:text-red-400">
                            Action requise — examinez ces incidents dès que possible.
                        </div>
                    </div>
                    <Button
                        variant="danger"
                        size="sm"
                        onClick={() => switchTab('pending')}
                    >
                        Voir
                    </Button>
                </div>
            )}

            {/* ── KPI Stats ── */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                {[
                    { label: 'En attente',     value: stats.pending,    icon: ShieldAlert,  iconColor: 'text-amber-600',   bgColor: 'bg-amber-100 dark:bg-amber-900/30',   tab: 'pending' },
                    { label: 'Confirmés',      value: stats.confirmed,  icon: ShieldX,      iconColor: 'text-red-600',     bgColor: 'bg-red-100 dark:bg-red-900/30',       tab: 'confirmed' },
                    { label: 'Examinés',       value: stats.reviewed,   icon: ShieldCheck,  iconColor: 'text-blue-600',    bgColor: 'bg-blue-100 dark:bg-blue-900/30',     tab: 'reviewed' },
                    { label: 'Dernières 24h',  value: stats.last_24h,   icon: Clock,        iconColor: 'text-purple-600',  bgColor: 'bg-purple-100 dark:bg-purple-900/30', tab: '' },
                    { label: 'Total',          value: stats.total,      icon: Shield,       iconColor: 'text-emerald-600', bgColor: 'bg-emerald-100 dark:bg-emerald-900/30', tab: '' },
                ].map((kpi) => (
                    <button
                        key={kpi.label}
                        onClick={() => kpi.tab !== undefined ? switchTab(kpi.tab) : undefined}
                        className="text-left w-full"
                    >
                        <Card className="hover:ring-2 hover:ring-[var(--color-primary)]/20 transition-shadow">
                            <div className="flex items-center gap-3">
                                <div className={`p-2.5 rounded-xl ${kpi.bgColor}`}>
                                    <kpi.icon size={20} className={kpi.iconColor} />
                                </div>
                                <div>
                                    <div className="text-xs text-[var(--color-text-muted)] uppercase font-medium">{kpi.label}</div>
                                    <div className="text-2xl font-bold text-[var(--color-text)]">{kpi.value}</div>
                                </div>
                            </div>
                        </Card>
                    </button>
                ))}
            </div>

            {/* ── Type breakdown pills ── */}
            {hasAnyIncident && (
                <div className="flex flex-wrap gap-2 mb-5">
                    {typeBreakdown.map((t) => {
                        const Icon = t.icon;
                        return (
                            <button
                                key={t.key}
                                onClick={() => {
                                    const next = { ...formFilters, type: formFilters.type === t.key ? '' : t.key };
                                    setFormFilters(next);
                                    applyFilters(next);
                                }}
                                className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border text-sm font-medium transition-all
                                    ${formFilters.type === t.key
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]'
                                    }`}
                            >
                                <Icon size={15} />
                                <span>{t.label}</span>
                                <span className={`ml-0.5 px-1.5 py-0.5 rounded-md text-xs font-bold ${t.count > 0 ? t.bg + ' ' + t.color : 'text-[var(--color-text-muted)]'}`}>
                                    {t.count}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            {/* ── Incident map ── */}
            {hasGeoIncidents && (
                <Card padding={false} className="mb-5">
                    <div className="px-5 pt-4 pb-2 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-[var(--color-text)] flex items-center gap-2">
                            <MapPin size={16} /> Carte des incidents ({mapMarkers.length})
                        </h3>
                    </div>
                    <div className="px-5 pb-5">
                        <LeafletMap markers={mapMarkers} height={320} />
                    </div>
                </Card>
            )}

            {/* ── Status tabs ── */}
            <div className="flex items-center gap-1 mb-4 overflow-x-auto pb-1">
                {STATUS_TABS.map((tab) => {
                    const isActive = activeTab === tab.key;
                    const count = tab.key === '' ? stats.total
                        : tab.key === 'pending' ? stats.pending
                        : tab.key === 'confirmed' ? stats.confirmed
                        : tab.key === 'reviewed' ? stats.reviewed
                        : stats.dismissed;
                    return (
                        <button
                            key={tab.key}
                            onClick={() => switchTab(tab.key)}
                            className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium whitespace-nowrap transition-all
                                ${isActive
                                    ? 'bg-[var(--color-primary)] text-white shadow-md shadow-[var(--color-primary)]/30'
                                    : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] border border-[var(--color-border)]'
                                }`}
                        >
                            <tab.icon size={15} />
                            {tab.label}
                            <span className={`px-1.5 py-0.5 rounded-md text-xs font-bold
                                ${isActive ? 'bg-white/20 text-white' : 'bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]'}`}>
                                {count}
                            </span>
                        </button>
                    );
                })}

                {/* Filters toggle on the right */}
                <div className="ml-auto">
                    <button
                        onClick={() => setFiltersOpen(!filtersOpen)}
                        className="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-sm font-medium border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] transition"
                    >
                        <Filter size={14} />
                        Filtres
                        {filtersOpen ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                    </button>
                </div>
            </div>

            {/* ── Collapsible filters ── */}
            {filtersOpen && (
                <Card className="mb-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div>
                            <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block font-medium">Type</label>
                            <select
                                value={formFilters.type}
                                onChange={(e) => setFormFilters({ ...formFilters, type: e.target.value })}
                                className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            >
                                <option value="">Tous les types</option>
                                {Object.entries(TYPE_CONFIG).map(([val, cfg]) => (
                                    <option key={val} value={val}>{cfg.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block font-medium">Sévérité</label>
                            <select
                                value={formFilters.severity}
                                onChange={(e) => setFormFilters({ ...formFilters, severity: e.target.value })}
                                className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            >
                                <option value="">Toutes</option>
                                <option value="high">Haute</option>
                                <option value="medium">Moyenne</option>
                                <option value="low">Basse</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block font-medium">Camion</label>
                            <select
                                value={formFilters.truck_id}
                                onChange={(e) => setFormFilters({ ...formFilters, truck_id: e.target.value })}
                                className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            >
                                <option value="">Tous les camions</option>
                                {trucks.map((t) => (
                                    <option key={t.id} value={t.id}>{t.matricule}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block font-medium">Du</label>
                            <input
                                type="date"
                                value={formFilters.from}
                                onChange={(e) => setFormFilters({ ...formFilters, from: e.target.value })}
                                className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block font-medium">Au</label>
                            <input
                                type="date"
                                value={formFilters.to}
                                onChange={(e) => setFormFilters({ ...formFilters, to: e.target.value })}
                                className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            />
                        </div>
                    </div>
                    <div className="flex gap-2 mt-4">
                        <Button size="sm" onClick={() => applyFilters()}>Appliquer</Button>
                        <Button size="sm" variant="secondary" onClick={resetFilters}>Réinitialiser</Button>
                    </div>
                </Card>
            )}

            {/* ── Data table ── */}
            {incidents.length > 0 ? (
                <Card padding={false}>
                    <div className="p-5">
                        <DataTable
                            data={incidents}
                            columns={[
                                {
                                    key: 'severity_dot',
                                    label: '',
                                    sortable: false,
                                    className: 'w-1 pr-0',
                                    render: (r) => (
                                        <span
                                            className={`inline-block w-2.5 h-2.5 rounded-full ${SEVERITY_MAP[r.severity]?.dot ?? 'bg-gray-300'}`}
                                            title={SEVERITY_MAP[r.severity]?.label}
                                        />
                                    ),
                                },
                                {
                                    key: 'type',
                                    label: 'Type',
                                    render: (r) => {
                                        const cfg = TYPE_CONFIG[r.type];
                                        if (!cfg) return r.type;
                                        const Icon = cfg.icon;
                                        return (
                                            <span className="inline-flex items-center gap-2">
                                                <span className={`p-1 rounded-md ${cfg.bg}`}>
                                                    <Icon size={13} className={cfg.color} />
                                                </span>
                                                <span className="text-[var(--color-text)] font-medium">{cfg.label}</span>
                                            </span>
                                        );
                                    },
                                },
                                {
                                    key: 'detected_at',
                                    label: 'Détecté',
                                    render: (r) => (
                                        <span className="text-[var(--color-text-secondary)] text-xs">{r.detected_at ?? '-'}</span>
                                    ),
                                },
                                {
                                    key: 'truck',
                                    label: 'Camion',
                                    sortable: false,
                                    render: (r) => r.truck ? (
                                        <a href={`/trucks/${r.truck.id}/show-page`} className="inline-flex items-center gap-1.5 text-[var(--color-primary)] hover:underline text-sm font-medium">
                                            <TruckIcon size={13} />
                                            {r.truck.matricule}
                                        </a>
                                    ) : <span className="text-[var(--color-text-muted)]">-</span>,
                                },
                                {
                                    key: 'title',
                                    label: 'Description',
                                    hideOnMobile: true,
                                    render: (r) => (
                                        <span className="text-[var(--color-text)] text-sm line-clamp-1" title={r.title}>
                                            {r.title}
                                        </span>
                                    ),
                                },
                                {
                                    key: 'transport_tracking',
                                    label: 'Mission',
                                    hideOnMobile: true,
                                    sortable: false,
                                    render: (r) => r.transport_tracking ? (
                                        <Badge variant="primary">{r.transport_tracking.reference}</Badge>
                                    ) : <span className="text-[var(--color-text-muted)]">-</span>,
                                },
                                {
                                    key: 'status',
                                    label: 'Statut',
                                    render: (r) => {
                                        const s = STATUS_MAP[r.status];
                                        return s ? <Badge variant={s.variant}>{s.label}</Badge> : r.status;
                                    },
                                },
                                {
                                    key: 'actions',
                                    label: '',
                                    sortable: false,
                                    className: 'w-1',
                                    render: (r) => (
                                        <a
                                            href={`/logistics/theft-incidents/${r.id}`}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-[var(--color-primary)]/10 text-[var(--color-primary)] hover:bg-[var(--color-primary)]/20 transition"
                                        >
                                            <Eye size={13} /> Détails
                                        </a>
                                    ),
                                },
                            ]}
                            searchable
                            exportable
                            exportFilename={`incidents-vol-${new Date().toISOString().slice(0, 10)}.csv`}
                            searchKeys={['title']}
                        />
                    </div>
                </Card>
            ) : (
                /* ── Professional empty state ── */
                <Card>
                    <div className="flex flex-col items-center justify-center py-16 px-4 text-center">
                        <div className="w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-6">
                            <ShieldCheck size={40} className="text-emerald-500" />
                        </div>
                        <h3 className="text-xl font-bold text-[var(--color-text)] mb-2">
                            Aucun incident détecté
                        </h3>
                        <p className="text-[var(--color-text-muted)] max-w-md mb-6 leading-relaxed">
                            Le système de détection analyse en continu les données de télémétrie, le carburant, les poids, les arrêts
                            et les itinéraires pour identifier automatiquement les incidents suspects.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl w-full">
                            {[
                                { icon: Fuel,       label: 'Vol de carburant',    desc: 'Baisse de niveau quand le moteur est éteint' },
                                { icon: Scale,      label: 'Écart de poids',      desc: 'Différence entre poids fournisseur et client' },
                                { icon: StopCircle, label: 'Arrêts suspects',     desc: 'Arrêts prolongés à des lieux inconnus' },
                            ].map((item) => (
                                <div key={item.label} className="p-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-hover)]/50">
                                    <item.icon size={20} className="text-[var(--color-primary)] mb-2" />
                                    <div className="text-sm font-semibold text-[var(--color-text)]">{item.label}</div>
                                    <div className="text-xs text-[var(--color-text-muted)] mt-1">{item.desc}</div>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-[var(--color-text-muted)] mt-6">
                            Les incidents apparaîtront ici dès que le système en détectera.
                            La synchronisation Fleeti s'exécute toutes les 30 minutes.
                        </p>
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
