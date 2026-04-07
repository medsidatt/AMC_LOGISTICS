import { usePage, router } from '@inertiajs/react';
import { useTheme } from '@/hooks/useTheme';
import { useState, useRef, useEffect } from 'react';
import { Menu, Sun, Moon, Bell, LogOut, ChevronLeft, ChevronRight, User, Settings } from 'lucide-react';

interface NavbarProps {
    onMenuToggle: () => void;
    onSidebarCollapse: () => void;
    sidebarCollapsed: boolean;
}

export default function Navbar({ onMenuToggle, onSidebarCollapse, sidebarCollapsed }: NavbarProps) {
    const { auth } = usePage().props;
    const { toggle, isDark } = useTheme();
    const [showMenu, setShowMenu] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClick = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) setShowMenu(false);
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const handleLogout = () => {
        router.post('/logout');
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

                <button className="relative p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">
                    <Bell size={18} />
                </button>

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
