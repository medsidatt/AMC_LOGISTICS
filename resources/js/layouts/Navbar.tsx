import { usePage, router, Link } from '@inertiajs/react';
import { useTheme } from '@/hooks/useTheme';
import { useState, useRef, useEffect } from 'react';
import { Menu, Sun, Moon, Bell, LogOut, ChevronLeft, ChevronRight, User, Settings } from 'lucide-react';

interface NavbarProps {
    onMenuToggle: () => void;
    onSidebarCollapse: () => void;
    sidebarCollapsed: boolean;
}

interface NotificationItem {
    id: string;
    data: Record<string, any>;
    read_at: string | null;
    created_human: string;
}

export default function Navbar({ onMenuToggle, onSidebarCollapse, sidebarCollapsed }: NavbarProps) {
    const { auth, notifications } = usePage().props as any;
    const { toggle, isDark } = useTheme();
    const [showMenu, setShowMenu] = useState(false);
    const [showBell, setShowBell] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const bellRef = useRef<HTMLDivElement>(null);

    const unreadCount: number = notifications?.unread_count ?? 0;
    const recent: NotificationItem[] = notifications?.recent ?? [];

    useEffect(() => {
        const handleClick = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) setShowMenu(false);
            if (bellRef.current && !bellRef.current.contains(e.target as Node)) setShowBell(false);
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const handleLogout = () => {
        router.post('/logout');
    };

    const openNotification = (n: NotificationItem) => {
        router.post(`/notifications/${n.id}/read`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setShowBell(false);
                if (n.data?.url) {
                    window.location.href = n.data.url;
                }
            },
        });
    };

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    return (
        <header className="sticky top-0 z-30 flex items-center justify-between h-16 px-4 lg:px-6 bg-[var(--color-surface)] border-b border-[var(--color-border)] shadow-sm">
            <div className="flex items-center gap-2">
                <button onClick={onMenuToggle} className="lg:hidden p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">
                    <Menu size={20} />
                </button>
                <button onClick={onSidebarCollapse} className="hidden lg:flex p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">
                    {sidebarCollapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
                </button>
            </div>

            <div className="flex items-center gap-1.5">
                <button onClick={toggle} className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] transition-colors" title={isDark ? 'Light mode' : 'Dark mode'}>
                    {isDark ? <Sun size={18} /> : <Moon size={18} />}
                </button>

                <div className="relative" ref={bellRef}>
                    <button
                        onClick={() => setShowBell((v) => !v)}
                        className="relative p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                        title="Notifications"
                    >
                        <Bell size={18} />
                        {unreadCount > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                                {unreadCount > 9 ? '9+' : unreadCount}
                            </span>
                        )}
                    </button>
                    {showBell && (
                        <div className="absolute right-0 top-full mt-2 w-80 max-h-[60vh] overflow-y-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg animate-fade-in">
                            <div className="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
                                <p className="text-sm font-semibold">Notifications</p>
                                {unreadCount > 0 && (
                                    <button onClick={markAllRead} className="text-xs text-[var(--color-primary)] hover:underline">Tout marquer lu</button>
                                )}
                            </div>
                            {recent.length === 0 ? (
                                <p className="px-4 py-6 text-sm text-[var(--color-text-muted)] text-center">Aucune notification.</p>
                            ) : (
                                <ul className="divide-y divide-[var(--color-border)]">
                                    {recent.map((n) => (
                                        <li key={n.id}>
                                            <button
                                                onClick={() => openNotification(n)}
                                                className={`w-full text-left px-4 py-3 hover:bg-[var(--color-surface-hover)] transition-colors ${!n.read_at ? 'bg-blue-50/40 dark:bg-blue-900/10' : ''}`}
                                            >
                                                <p className="text-sm font-medium">
                                                    Inspection soumise — {n.data?.truck_matricule ?? 'camion inconnu'}
                                                </p>
                                                <p className="text-xs text-[var(--color-text-muted)] mt-0.5">
                                                    Par {n.data?.inspector_name ?? '—'} le {n.data?.inspection_date ?? '—'}
                                                </p>
                                                <p className="text-xs text-[var(--color-text-muted)] mt-0.5">{n.created_human}</p>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <div className="px-4 py-2 border-t border-[var(--color-border)] text-right">
                                <Link href="/notifications" className="text-xs text-[var(--color-primary)] hover:underline">Voir toutes →</Link>
                            </div>
                        </div>
                    )}
                </div>

                {/* User dropdown */}
                <div className="relative ml-2 pl-3 border-l border-[var(--color-border)]" ref={menuRef}>
                    <button onClick={() => setShowMenu(!showMenu)} className="flex items-center gap-3 hover:opacity-80 transition-opacity">
                        <div className="hidden sm:block text-right">
                            <p className="text-sm font-medium text-[var(--color-text)] leading-tight">{auth.user?.name}</p>
                            <p className="text-xs text-[var(--color-text-muted)]">{auth.roles[0] ?? 'User'}</p>
                        </div>
                        <div className="w-9 h-9 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-semibold">
                            {auth.user?.name?.charAt(0).toUpperCase()}
                        </div>
                    </button>

                    {showMenu && (
                        <div className="absolute right-0 top-full mt-2 w-56 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg py-1 animate-fade-in">
                            <div className="px-4 py-3 border-b border-[var(--color-border)]">
                                <p className="text-sm font-medium text-[var(--color-text)]">{auth.user?.name}</p>
                                <p className="text-xs text-[var(--color-text-muted)]">{auth.user?.email}</p>
                            </div>
                            <a href="/auth/profile" className="flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--color-text)] hover:bg-[var(--color-surface-hover)] transition-colors" onClick={() => setShowMenu(false)}>
                                <User size={16} className="text-[var(--color-text-muted)]" /> Mon profil
                            </a>
                            <a href="/auth/account" className="flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--color-text)] hover:bg-[var(--color-surface-hover)] transition-colors" onClick={() => setShowMenu(false)}>
                                <Settings size={16} className="text-[var(--color-text-muted)]" /> Paramètres
                            </a>
                            <div className="border-t border-[var(--color-border)] mt-1 pt-1">
                                <button onClick={handleLogout} className="flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--color-danger)] hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left transition-colors">
                                    <LogOut size={16} /> Déconnexion
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
