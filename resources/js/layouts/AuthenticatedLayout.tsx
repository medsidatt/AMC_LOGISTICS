import { useState, useEffect, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from './Sidebar';
import Navbar from './Navbar';
import Toast from '@/components/ui/Toast';
import { clsx } from 'clsx';

interface Props {
    children: ReactNode;
    title?: string;
}

export default function AuthenticatedLayout({ children, title }: Props) {
    const { flash } = usePage().props;
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem('amc-sidebar-collapsed') === 'true';
    });
    const [mobileOpen, setMobileOpen] = useState(false);
    const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

    useEffect(() => {
        localStorage.setItem('amc-sidebar-collapsed', String(sidebarCollapsed));
    }, [sidebarCollapsed]);

    useEffect(() => {
        if (flash.success) setToast({ message: flash.success, type: 'success' });
        if (flash.error) setToast({ message: flash.error, type: 'error' });
    }, [flash.success, flash.error]);

    return (
        <div className="min-h-screen bg-[var(--color-bg)]">
            <Sidebar
                collapsed={sidebarCollapsed}
                onClose={() => setMobileOpen(false)}
                mobileOpen={mobileOpen}
            />

            <div
                className={clsx(
                    'transition-all duration-300',
                    sidebarCollapsed ? 'lg:ml-[68px]' : 'lg:ml-[260px]',
                )}
            >
                <Navbar
                    onMenuToggle={() => setMobileOpen((prev) => !prev)}
                    onSidebarCollapse={() => setSidebarCollapsed((prev) => !prev)}
                    sidebarCollapsed={sidebarCollapsed}
                />

                <main className="p-4 lg:p-6 animate-fade-in">
                    {title && (
                        <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">
                            {title}
                        </h1>
                    )}
                    {children}
                </main>
            </div>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </div>
    );
}
