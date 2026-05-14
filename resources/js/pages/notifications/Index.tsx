import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { Bell, ShieldCheck } from 'lucide-react';

interface NotificationItem {
    id: string;
    type: string;
    data: Record<string, any>;
    read_at: string | null;
    created_at: string | null;
    created_human: string | null;
}

interface Props {
    notifications: NotificationItem[];
}

export default function NotificationsIndex({ notifications }: Props) {
    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    const open = (n: NotificationItem) => {
        router.post(`/notifications/${n.id}/read`, {}, {
            preserveScroll: true,
            onFinish: () => {
                if (n.data?.url) window.location.href = n.data.url;
            },
        });
    };

    const unreadCount = notifications.filter((n) => !n.read_at).length;

    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />
            <div className="space-y-4 max-w-3xl">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Bell size={22} className="text-amber-500" />
                        <h1 className="text-xl font-semibold">Notifications</h1>
                    </div>
                    {unreadCount > 0 && (
                        <Button variant="secondary" onClick={markAllRead}>Tout marquer lu</Button>
                    )}
                </div>

                <Card padding={false}>
                    {notifications.length === 0 ? (
                        <p className="px-4 py-12 text-center text-[var(--color-text-muted)]">Aucune notification.</p>
                    ) : (
                        <ul className="divide-y divide-[var(--color-border)]">
                            {notifications.map((n) => (
                                <li key={n.id}>
                                    <button
                                        onClick={() => open(n)}
                                        className={`w-full text-left px-4 py-3 hover:bg-[var(--color-surface-hover)] transition-colors ${!n.read_at ? 'bg-blue-50/40 dark:bg-blue-900/10' : ''}`}
                                    >
                                        <div className="flex items-start gap-3">
                                            <ShieldCheck size={18} className="text-emerald-500 mt-0.5" />
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">
                                                    Inspection soumise — {n.data?.truck_matricule ?? 'camion inconnu'}
                                                </p>
                                                <p className="text-xs text-[var(--color-text-muted)] mt-0.5">
                                                    Par {n.data?.inspector_name ?? '—'} le {n.data?.inspection_date ?? '—'}
                                                </p>
                                                <p className="text-xs text-[var(--color-text-muted)] mt-0.5">{n.created_human}</p>
                                            </div>
                                            {!n.read_at && <span className="w-2 h-2 rounded-full bg-blue-500 mt-2" />}
                                        </div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
