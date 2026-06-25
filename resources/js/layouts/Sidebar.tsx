import {usePage} from '@inertiajs/react';
import {
    LayoutDashboard, List, BarChart3, Factory, Truck, IdCard, Network,
    Wrench, Users, Mail, ShieldCheck, FileSpreadsheet,
    ClipboardCheck, Route, X, Map, ShieldAlert, MapPin, Settings, Fuel,
    AlertTriangle, Activity, History, Target, ChevronDown, CalendarDays, CalendarOff,
    CalendarRange, Send, FileWarning, Package,
} from 'lucide-react';
import {useState, useEffect, Fragment, type ReactNode} from 'react';
import {clsx} from 'clsx';
import {usePermission} from '@/hooks/usePermission';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    match?: string;
    /** Any-of these permissions reveals the item. Omit for always-visible. */
    permission?: string | string[];
    /** Any-of these roles reveals the item (for routes gated by role, not permission). */
    role?: string | string[];
    /** Live count badge fed from shared `operationsBadges`. */
    badge?: 'missing' | 'exceptions';
    /** Render a divider above this item (used to set the cockpit apart). */
    separatorBefore?: boolean;
}

interface NavSection {
    header: string;
    items: NavItem[];
}

type PermCheck = (p: string | string[]) => boolean;

function itemVisible(item: NavItem, can: PermCheck, hasRole: PermCheck): boolean {
    const passPerm = item.permission === undefined || can(item.permission);
    const passRole = item.role === undefined || hasRole(item.role);
    return passPerm && passRole;
}

function pathFor(item: NavItem): string {
    return item.match ?? item.href;
}

function pickActiveHref(url: string, items: NavItem[]): string | null {
    let best: { item: NavItem; len: number } | null = null;
    for (const item of items) {
        const path = pathFor(item);
        const matches = item.match
            ? url.startsWith(item.match)
            : url === item.href || url.startsWith(item.href + '/');
        if (!matches) continue;
        if (!best || path.length > best.len) {
            best = {item, len: path.length};
        }
    }
    return best ? best.item.href : null;
}

function SidebarLink({item, collapsed, activeHref, badgeCount}: { item: NavItem; collapsed: boolean; activeHref: string | null; badgeCount?: number }) {
    const isActive = item.href === activeHref;
    const showBadge = typeof badgeCount === 'number' && badgeCount > 0;
    const badgeColor = item.badge === 'missing' ? 'bg-red-500' : 'bg-amber-500';

    return (
        <li>
            <a
                href={item.href}
                className={clsx(
                    'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200',
                    isActive
                        ? 'bg-[var(--color-primary)] text-white shadow-md shadow-[var(--color-primary)]/30'
                        : 'text-[var(--color-sidebar-text)] hover:bg-[var(--color-sidebar-hover)]',
                    collapsed && 'justify-center px-2',
                )}
                title={collapsed ? item.label : undefined}
            >
                <span className="flex-shrink-0 w-5 h-5 relative">
                    {item.icon}
                    {collapsed && showBadge && (
                        <span className={clsx('absolute -top-1.5 -right-1.5 w-2 h-2 rounded-full ring-2 ring-[var(--color-sidebar-bg)]', badgeColor)} />
                    )}
                </span>
                {!collapsed && <span className="truncate flex-1">{item.label}</span>}
                {!collapsed && showBadge && (
                    <span className={clsx('inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-semibold text-white', badgeColor)}>
                        {badgeCount}
                    </span>
                )}
            </a>
        </li>
    );
}

/**
 * Single source of truth for navigation. Each item declares the permission
 * (or role, for the handful of role-gated routes) that reveals it. Items the
 * user can't access are filtered out and empty sections collapse. Permission
 * names mirror the controller `__construct` middleware exactly.
 */
const mainSections: NavSection[] = [
    {
        // Operations — the daily logistics lifecycle, one route per stage.
        header: 'Opérations',
        items: [
            {label: 'Planification', href: '/planning', match: '/planning', icon: <CalendarRange size={18}/>, permission: 'fleet-roster-plan'},
            {label: 'Répartition', href: '/dispatch', match: '/dispatch', icon: <Send size={18}/>, permission: 'daily-dispatch-list'},
            {label: 'Transports', href: '/transport_tracking', match: '/transport_tracking', icon: <Package size={18}/>, permission: 'transport-tracking-list'},
            {label: 'Réalisation', href: '/realisation', match: '/realisation', icon: <Activity size={18}/>, permission: 'daily-dispatch-list'},
            {label: 'Réconciliation', href: '/reconciliation', match: '/reconciliation', icon: <FileWarning size={18}/>, permission: 'live-fleet-view', badge: 'missing'},
        ],
    },
    {
        header: 'Ressources',
        items: [
            {label: 'Camions', href: '/trucks', icon: <Truck size={18}/>, permission: 'truck-list'},
            {label: 'Conducteurs', href: '/drivers', icon: <IdCard size={18}/>, permission: 'driver-list'},
            {label: 'Affectations', href: '/assignments', match: '/assignments', icon: <Network size={18}/>, permission: 'driver-truck-assign'},
        ],
    },
    {
        header: 'Maintenance',
        items: [
            {label: 'Maintenance', href: '/maintenance', icon: <Wrench size={18}/>, match: '/maintenance', permission: 'maintenance-list'},
        ],
    },
    {
        header: 'Conformité',
        items: [
            {label: 'Inspections', href: '/hse/inspections', icon: <ShieldCheck size={18}/>, match: '/hse/inspections', permission: 'inspection-list'},
        ],
    },
    {
        header: 'Administration',
        items: [
            {label: 'Paramètres', href: '/settings/fleet', icon: <Settings size={18}/>, match: '/settings/fleet', permission: 'fleet-settings-edit'},
            {label: 'Utilisateurs', href: '/users', icon: <Users size={18}/>, permission: 'user-list'},
            {label: 'Rôles', href: '/roles', icon: <ShieldCheck size={18}/>, permission: 'role-list'},
            {label: 'Journal', href: '/admin/audit-logs', icon: <Activity size={18}/>, match: '/admin/audit-logs', permission: 'audit-log-view'},
        ],
    },
];

const accountSection: NavSection = {
    header: 'Compte',
    items: [
        {label: 'Mon profil', href: '/auth/profile', icon: <Users size={18}/>, match: '/auth/profile'},
    ],
};

// Driver self-service space. These routes are gated by the Driver role (not
// permissions), so the whole block is shown only to drivers.
const driverSections: NavSection[] = [
    {
        header: 'Mon espace',
        items: [
            {label: 'Checklist hebdomadaire', href: '/drivers/checklist-page', icon: <ClipboardCheck size={18}/>},
            {label: 'Signaler un problème', href: '/drivers/issues', icon: <AlertTriangle size={18}/>},
            {label: 'Mes voyages', href: '/drivers/my-trips', icon: <Route size={18}/>},
            {label: 'Mon camion', href: '/drivers/my-truck', icon: <Truck size={18}/>},
        ],
    },
];

interface SidebarProps {
    collapsed: boolean;
    onClose: () => void;
    mobileOpen: boolean;
}

export default function Sidebar({collapsed, onClose, mobileOpen}: SidebarProps) {
    const {url, props} = usePage();
    const {can, hasRole} = usePermission();

    const badges = (props as { operationsBadges?: { missing?: number; exceptions?: number } | null }).operationsBadges ?? null;
    const badgeFor = (item: NavItem): number | undefined =>
        item.badge ? (badges?.[item.badge] ?? 0) : undefined;

    // Drivers get a dedicated self-service space; everyone else gets the
    // permission-filtered main navigation (admins see all, custom roles see
    // exactly what their permissions allow).
    const rawSections: NavSection[] = hasRole('Driver')
        ? [...driverSections, accountSection]
        : [...mainSections, accountSection];

    const sections: NavSection[] = rawSections
        .map((s) => ({...s, items: s.items.filter((i) => itemVisible(i, can, hasRole))}))
        .filter((s) => s.items.length > 0);

    const dashboardItem: NavItem = {
        label: 'Dashboard',
        href: '/dashboard',
        icon: <LayoutDashboard size={18}/>,
    };
    const allItems: NavItem[] = [dashboardItem, ...sections.flatMap((s) => s.items)];
    const activeHref = pickActiveHref(url, allItems);
    const activeSection = sections.find((s) => s.items.some((i) => i.href === activeHref))?.header ?? null;

    // Collapsible groups (expanded mode only). State persists; the section
    // containing the current page is always shown so the user never loses context.
    const [openSections, setOpenSections] = useState<Set<string>>(() => {
        if (typeof window !== 'undefined') {
            const raw = localStorage.getItem('amc-sidebar-open-sections');
            if (raw) {
                try { return new Set<string>(JSON.parse(raw)); } catch { /* fall through */ }
            }
        }
        return new Set(rawSections.map((s) => s.header));
    });
    useEffect(() => {
        localStorage.setItem('amc-sidebar-open-sections', JSON.stringify([...openSections]));
    }, [openSections]);
    const toggleSection = (h: string) =>
        setOpenSections((prev) => {
            const next = new Set(prev);
            next.has(h) ? next.delete(h) : next.add(h);
            return next;
        });

    return (
        <>
            {mobileOpen && (
                <div
                    className="fixed inset-0 bg-black/50 z-40 lg:hidden"
                    onClick={onClose}
                />
            )}

            <aside
                className={clsx(
                    'fixed top-0 left-0 z-50 h-full bg-[var(--color-sidebar-bg)] transition-all duration-300 flex flex-col',
                    collapsed ? 'w-[68px]' : 'w-[260px]',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                )}
            >
                {/* Logo */}
                <div
                    className="flex items-center justify-between h-16 px-4 border-b border-[var(--color-sidebar-border)]">
                    {!collapsed && (
                        <div className="flex items-center gap-2">
                            <img src="/images/logo.png" alt="" className="w-8 h-8 object-contain shrink-0"/>
                            <span className="text-lg font-bold text-[var(--color-sidebar-title)] tracking-tight">
                                AMC <span className="text-[var(--color-primary)]">Travaux SN</span>
                            </span>
                        </div>
                    )}
                    {collapsed && (
                        <img src="/images/logo.png" alt="AMC Travaux SN" className="w-8 h-8 object-contain mx-auto"/>
                    )}
                    <button
                        onClick={onClose}
                        className="lg:hidden text-[var(--color-sidebar-muted)] hover:text-[var(--color-sidebar-text)] p-1"
                    >
                        <X size={20}/>
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 overflow-y-auto py-3 px-2">
                    <ul className="space-y-0.5">
                        <SidebarLink item={dashboardItem} collapsed={collapsed} activeHref={activeHref}/>

                        {sections.map((section) => {
                            const isOpen = collapsed || openSections.has(section.header) || section.header === activeSection;
                            return (
                                <div key={section.header}>
                                    {collapsed ? (
                                        <li className="my-2 border-t border-[var(--color-sidebar-border)]"/>
                                    ) : (
                                        <li className="px-2 pt-3 pb-1">
                                            <button
                                                type="button"
                                                onClick={() => toggleSection(section.header)}
                                                aria-expanded={isOpen}
                                                className="w-full flex items-center justify-between px-2 py-1 rounded-md text-xs font-semibold uppercase tracking-wider text-[var(--color-sidebar-muted)] hover:bg-[var(--color-sidebar-hover)] transition-colors cursor-pointer"
                                            >
                                                <span className="truncate">{section.header}</span>
                                                <ChevronDown size={14} className={clsx('shrink-0 transition-transform', !isOpen && '-rotate-90')}/>
                                            </button>
                                        </li>
                                    )}
                                    {isOpen && section.items.map((item) => (
                                        <Fragment key={item.href}>
                                            {item.separatorBefore && (
                                                <li className="my-1.5 border-t border-[var(--color-sidebar-border)]" />
                                            )}
                                            <SidebarLink item={item} collapsed={collapsed}
                                                         activeHref={activeHref} badgeCount={badgeFor(item)}/>
                                        </Fragment>
                                    ))}
                                </div>
                            );
                        })}
                    </ul>
                </nav>

                {/* Footer */}
                {!collapsed && (
                    <div className="px-4 py-3 border-t border-[var(--color-sidebar-border)]">
                        <p className="text-xs text-[var(--color-sidebar-muted)] text-center">AMC Travaux SN</p>
                    </div>
                )}
            </aside>
        </>
    );
}
