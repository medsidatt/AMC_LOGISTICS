import { usePage } from '@inertiajs/react';
import { LayoutDashboard, Satellite, Weight } from 'lucide-react';
import { clsx } from 'clsx';

const tabs = [
    { label: 'Vue générale', href: '/dashboard/trackings', icon: <LayoutDashboard size={16} />, match: '/dashboard/trackings' },
    { label: 'Fleeti & Carburant', href: '/dashboard/fleeti', icon: <Satellite size={16} />, match: '/dashboard/fleeti' },
    { label: 'Rotations & Poids', href: '/dashboard/rotations', icon: <Weight size={16} />, match: '/dashboard/rotations' },
];

export default function AnalyticsTabs() {
    const { url } = usePage();

    return (
        <div className="flex flex-wrap items-center gap-2 mb-6">
            {tabs.map((tab) => {
                const isActive = url.startsWith(tab.match);
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
