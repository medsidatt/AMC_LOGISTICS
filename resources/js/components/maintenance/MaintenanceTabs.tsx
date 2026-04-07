import { usePage } from '@inertiajs/react';
import { Truck, ScrollText, History } from 'lucide-react';
import { clsx } from 'clsx';

const tabs = [
    { label: "Vue d'ensemble", href: '/maintenance', icon: <Truck size={16} />, match: '/maintenance' },
    { label: 'Règles', href: '/maintenance/rules', icon: <ScrollText size={16} />, match: '/maintenance/rules' },
    { label: 'Historique', href: '/maintenance/history', icon: <History size={16} />, match: '/maintenance/history' },
];

export default function MaintenanceTabs() {
    const { url } = usePage();

    return (
        <div className="flex flex-wrap items-center gap-2 mb-6">
            {tabs.map((tab) => {
                const isActive = tab.match === '/maintenance'
                    ? url === '/maintenance' || url === '/maintenance/'
                    : url.startsWith(tab.match);
                return (
                    <a key={tab.href} href={tab.href}
                        className={clsx(
                            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition',
                            isActive
                                ? 'bg-[var(--color-primary)] text-white'
                                : 'bg-[var(--color-surface)] border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]',
                        )}>
                        {tab.icon} {tab.label}
                    </a>
                );
            })}
        </div>
    );
}
