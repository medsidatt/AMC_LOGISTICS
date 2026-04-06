import { usePage } from '@inertiajs/react';
import {
    LayoutDashboard, List, BarChart3, Factory, Truck, IdCard, Network,
    Wrench, Users, Mail, ShieldCheck, FolderOpen, Building2,
    ClipboardCheck, Route, X, ChevronDown, ChevronRight,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { clsx } from 'clsx';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    match?: string;
}

interface NavSection {
    header: string;
    items: NavItem[];
}

function SidebarLink({ item, collapsed }: { item: NavItem; collapsed: boolean }) {
    const { url } = usePage();
    const isActive = item.match
        ? url.startsWith(item.match)
        : url === item.href || url.startsWith(item.href + '/');

    return (
        <li>
            <a
                href={item.href}
                className={clsx(
                    'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200',
                    'hover:bg-white/10',
                    isActive
                        ? 'bg-[var(--color-primary)] text-white shadow-md shadow-[var(--color-primary)]/30'
                        : 'text-[var(--color-sidebar-text)]',
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

function SectionHeader({ label, collapsed }: { label: string; collapsed: boolean }) {
    if (collapsed) {
        return <li className="my-2 border-t border-white/10" />;
    }
    return (
        <li className="px-4 pt-4 pb-1">
            <span className="text-xs font-semibold uppercase tracking-wider text-white/40">
                {label}
            </span>
        </li>
    );
}

const dataSections: NavSection[] = [
    {
        header: 'Transport',
        items: [
            { label: 'Suivi Transport', href: '/transport_tracking', icon: <List size={18} /> },
            { label: 'Dashboard Analytics', href: '/dashboard/trackings', icon: <BarChart3 size={18} />, match: '/dashboard/trackings' },
            { label: 'Fournisseurs', href: '/providers', icon: <Factory size={18} /> },
        ],
    },
    {
        header: 'Flotte',
        items: [
            { label: 'Camions', href: '/trucks', icon: <Truck size={18} /> },
            { label: 'Conducteurs', href: '/drivers', icon: <IdCard size={18} /> },
            { label: 'Transporteurs', href: '/transporters', icon: <Network size={18} /> },
        ],
    },
    {
        header: 'Maintenance',
        items: [
            { label: 'Tableau de bord', href: '/logistics/dashboard', icon: <Wrench size={18} />, match: '/logistics' },
        ],
    },
    {
        header: 'Organisation',
        items: [
            { label: 'Projets', href: '/projects', icon: <FolderOpen size={18} /> },
            { label: 'Entités', href: '/entities', icon: <Building2 size={18} /> },
        ],
    },
];

const adminSection: NavSection = {
    header: 'Administration',
    items: [
        { label: 'Utilisateurs', href: '/users', icon: <Users size={18} /> },
        { label: 'Invitations', href: '/auth/invitations', icon: <Mail size={18} />, match: '/auth/invitations' },
        { label: 'Rôles', href: '/roles', icon: <ShieldCheck size={18} /> },
    ],
};

const driverSections: NavSection[] = [
    {
        header: 'Mon espace',
        items: [
            { label: 'Checklist quotidien', href: '/drivers/checklist', icon: <ClipboardCheck size={18} /> },
            { label: 'Mes voyages', href: '/drivers/my-trips', icon: <Route size={18} /> },
            { label: 'Mon camion', href: '/drivers/my-truck', icon: <Truck size={18} /> },
        ],
    },
];

interface SidebarProps {
    collapsed: boolean;
    onClose: () => void;
    mobileOpen: boolean;
}

export default function Sidebar({ collapsed, onClose, mobileOpen }: SidebarProps) {
    const { auth } = usePage().props;
    const isDriver = auth.roles.includes('Driver');
    const isAdmin = auth.roles.includes('Admin') || auth.roles.includes('Super Admin');

    let sections: NavSection[];
    if (isDriver) {
        sections = driverSections;
    } else if (isAdmin) {
        sections = [...dataSections, adminSection];
    } else {
        // Manager and other roles: data only, no admin section
        sections = dataSections;
    }

    return (
        <>
            {/* Mobile overlay */}
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
                <div className="flex items-center justify-between h-16 px-4 border-b border-white/10">
                    {!collapsed && (
                        <span className="text-lg font-bold text-white tracking-tight">
                            AMC <span className="text-[var(--color-primary-light)]">Logistics</span>
                        </span>
                    )}
                    {collapsed && (
                        <span className="text-lg font-bold text-[var(--color-primary-light)] mx-auto">A</span>
                    )}
                    <button
                        onClick={onClose}
                        className="lg:hidden text-white/60 hover:text-white p-1"
                    >
                        <X size={20} />
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 overflow-y-auto py-3 px-2">
                    <ul className="space-y-0.5">
                        {/* Dashboard link */}
                        <SidebarLink
                            item={{
                                label: 'Dashboard',
                                href: '/dashboard',
                                icon: <LayoutDashboard size={18} />,
                                match: '/dashboard',
                            }}
                            collapsed={collapsed}
                        />

                        {sections.map((section) => (
                            <div key={section.header}>
                                <SectionHeader label={section.header} collapsed={collapsed} />
                                {section.items.map((item) => (
                                    <SidebarLink key={item.href} item={item} collapsed={collapsed} />
                                ))}
                            </div>
                        ))}
                    </ul>
                </nav>

                {/* Footer */}
                {!collapsed && (
                    <div className="px-4 py-3 border-t border-white/10">
                        <p className="text-xs text-white/30 text-center">AMC Travaux SN</p>
                    </div>
                )}
            </aside>
        </>
    );
}
