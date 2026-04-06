import { usePage, router } from '@inertiajs/react';
import { useTheme } from '@/hooks/useTheme';
import { Menu, Sun, Moon, Bell, LogOut, ChevronLeft, ChevronRight } from 'lucide-react';
import { clsx } from 'clsx';

interface NavbarProps {
    onMenuToggle: () => void;
    onSidebarCollapse: () => void;
    sidebarCollapsed: boolean;
}

export default function Navbar({ onMenuToggle, onSidebarCollapse, sidebarCollapsed }: NavbarProps) {
    const { auth } = usePage().props;
    const { toggle, isDark } = useTheme();

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <header className="sticky top-0 z-30 flex items-center justify-between h-16 px-4 lg:px-6 bg-[var(--color-surface)] border-b border-[var(--color-border)] shadow-sm">
            <div className="flex items-center gap-2">
                {/* Mobile menu button */}
                <button
                    onClick={onMenuToggle}
                    className="lg:hidden p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                >
                    <Menu size={20} />
                </button>

                {/* Desktop collapse button */}
                <button
                    onClick={onSidebarCollapse}
                    className="hidden lg:flex p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"
                >
                    {sidebarCollapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
                </button>
            </div>

            <div className="flex items-center gap-1.5">
                {/* Theme toggle */}
                <button
                    onClick={toggle}
                    className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] transition-colors"
                    title={isDark ? 'Light mode' : 'Dark mode'}
                >
                    {isDark ? <Sun size={18} /> : <Moon size={18} />}
                </button>

                {/* Notifications */}
                <button className="relative p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]">
                    <Bell size={18} />
                </button>

                {/* User menu */}
                <div className="flex items-center gap-3 ml-2 pl-3 border-l border-[var(--color-border)]">
                    <div className="hidden sm:block text-right">
                        <p className="text-sm font-medium text-[var(--color-text)] leading-tight">
                            {auth.user?.name}
                        </p>
                        <p className="text-xs text-[var(--color-text-muted)]">
                            {auth.roles[0] ?? 'User'}
                        </p>
                    </div>

                    <div className="w-9 h-9 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-semibold">
                        {auth.user?.name?.charAt(0).toUpperCase()}
                    </div>

                    <button
                        onClick={handleLogout}
                        className="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-[var(--color-text-muted)] hover:text-[var(--color-danger)] transition-colors"
                        title="Logout"
                    >
                        <LogOut size={18} />
                    </button>
                </div>
            </div>
        </header>
    );
}
