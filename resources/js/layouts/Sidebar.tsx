import {usePage} from '@inertiajs/react';
import {
    LayoutDashboard, List, BarChart3, Factory, Truck, IdCard, Network,
    Wrench, Users, Mail, ShieldCheck, FileSpreadsheet,
    ClipboardCheck, Route, X, Map, ShieldAlert, MapPin, Settings, Fuel,
    AlertTriangle, Activity, History, Target,
} from 'lucide-react';
import {type ReactNode} from 'react';
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

function SidebarLink({item, collapsed, activeHref}: { item: NavItem; collapsed: boolean; activeHref: string | null }) {
    const isActive = item.href === activeHref;

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
                <span className="flex-shrink-0 w-5 h-5">{item.icon}</span>
                {!collapsed && <span className="truncate">{item.label}</span>}
            </a>
        </li>
    );
}

function SectionHeader({label, collapsed}: { label: string; collapsed: boolean }) {
    if (collapsed) {
        return <li className="my-2 border-t border-[var(--color-sidebar-border)]"/>;
    }
    return (
        <li className="px-4 pt-4 pb-1">
            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--color-sidebar-muted)]">
                {label}
            </span>
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
        header: 'Transport',
        items: [
            {label: 'Suivi Transport', href: '/transport_tracking', icon: <List size={18}/>, permission: 'transport-tracking-list'},
            {label: 'Analytiques', href: '/dashboard/trackings', icon: <BarChart3 size={18}/>, match: '/dashboard/', permission: 'transport-tracking-list'},
            {label: 'Fournisseurs', href: '/providers', icon: <Factory size={18}/>, permission: 'provider-list'},
            {label: 'Rapports', href: '/reports', icon: <FileSpreadsheet size={18}/>, match: '/reports', permission: 'report-view'},
        ],
    },
    {
        header: 'Flotte',
        items: [
            {label: 'Camions', href: '/trucks', icon: <Truck size={18}/>, permission: 'truck-list'},
            {label: 'Conducteurs', href: '/drivers', icon: <IdCard size={18}/>, permission: 'driver-list'},
            {label: 'Affectations', href: '/logistics/affectations', icon: <IdCard size={18}/>, match: '/logistics/affectations', permission: 'driver-truck-assign'},
            {label: 'Transporteurs', href: '/transporters', icon: <Network size={18}/>, permission: 'transporter-list'},
        ],
    },
    {
        header: 'Maintenance',
        items: [
            {label: 'Vue d\'ensemble', href: '/maintenance', icon: <Wrench size={18}/>, match: '/maintenance', permission: 'maintenance-list'},
            {label: 'Tableau logistique', href: '/logistics/dashboard', icon: <ClipboardCheck size={18}/>, match: '/logistics/dashboard', permission: 'logistics-dashboard'},
        ],
    },
    {
        header: 'Inspections',
        items: [
            {label: 'Inspections', href: '/hse/inspections', icon: <ShieldCheck size={18}/>, match: '/hse/inspections', permission: 'inspection-list'},
            {label: 'Checklists hebdo', href: '/logistics/validation/checklists', icon: <ClipboardCheck size={18}/>, match: '/logistics/validation/checklists', permission: 'weekly-checklist-validate'},
        ],
    },
    {
        header: 'Planification',
        items: [
            {label: 'Programmation rotations', href: '/logistics/planning', icon: <Users size={18}/>, match: '/logistics/planning', permission: 'daily-dispatch-list'},
            {label: 'Suivi planification', href: '/logistics/planning/weekly', icon: <Activity size={18}/>, match: '/logistics/planning/weekly', permission: 'daily-dispatch-list'},
            {label: 'Objectifs', href: '/logistics/objectives', icon: <Target size={18}/>, match: '/logistics/objectives', permission: 'fleet-roster-plan'},
            {label: 'Planning flotte', href: '/logistics/fleet-roster', icon: <Truck size={18}/>, match: '/logistics/fleet-roster', permission: 'fleet-roster-plan'},
            {label: 'Historique objectifs', href: '/logistics/fleet-roster/history', icon: <History size={18}/>, match: '/logistics/fleet-roster/history', permission: 'fleet-roster-plan'},
            {label: 'Paramètres flotte', href: '/settings/fleet', icon: <Settings size={18}/>, match: '/settings/fleet', permission: 'fleet-settings-edit'},
        ],
    },
    {
        header: 'Sécurité',
        items: [
            {label: 'Cartographie flotte', href: '/logistics/fleet-map', icon: <Map size={18}/>, match: '/logistics/fleet-map', permission: 'fleet-map-view'},
            {label: 'Incidents de vol', href: '/logistics/theft-incidents', icon: <ShieldAlert size={18}/>, match: '/logistics/theft-incidents', permission: 'logistics-dashboard'},
            {label: 'Lieux (géofences)', href: '/logistics/places', icon: <MapPin size={18}/>, match: '/logistics/places', permission: 'logistics-dashboard'},
        ],
    },
    {
        header: 'Administration',
        items: [
            {label: 'Utilisateurs', href: '/users', icon: <Users size={18}/>, permission: 'user-list'},
            {label: 'Invitations', href: '/auth/invitations', icon: <Mail size={18}/>, match: '/auth/invitations', permission: 'invitation-list'},
            {label: 'Rôles', href: '/roles', icon: <ShieldCheck size={18}/>, permission: 'role-list'},
            {label: 'Import carburant', href: '/fuel/import', icon: <Fuel size={18}/>, match: '/fuel/import', permission: 'fuel-import'},
            {label: 'Journal des objectifs', href: '/logistics/objective-history', icon: <History size={18}/>, match: '/logistics/objective-history', permission: 'objective-history-list'},
            {label: 'Journal d\'activité', href: '/admin/audit-logs', icon: <Activity size={18}/>, match: '/admin/audit-logs', permission: 'audit-log-view'},
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
    const {url} = usePage();
    const {can, hasRole} = usePermission();

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

                        {sections.map((section) => (
                            <div key={section.header}>
                                <SectionHeader label={section.header} collapsed={collapsed}/>
                                {section.items.map((item) => (
                                    <SidebarLink key={item.href} item={item} collapsed={collapsed}
                                                 activeHref={activeHref}/>
                                ))}
                            </div>
                        ))}
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
