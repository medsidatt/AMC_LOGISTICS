import { Head, usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';

interface Props {
    email: string;
    roleName?: string | null;
}

export default function AcceptInvitation({ email, roleName }: Props) {
    const { flash } = usePage<{ flash: { error?: string | null } }>().props;

    return (
        <GuestLayout title="Accepter l'invitation">
            <Head title="Invitation" />

            {flash?.error && (
                <div className="mb-4 p-3 rounded-lg border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 text-sm text-[var(--color-danger)]">
                    {flash.error}
                </div>
            )}

            <p className="text-sm text-[var(--color-text-secondary)] mb-2">
                Vous avez été invité à rejoindre AMC Logistics
                {roleName ? <> en tant que <span className="font-medium">{roleName}</span></> : null}.
            </p>
            <p className="text-sm text-[var(--color-text-secondary)] mb-6">
                Connectez-vous avec votre compte Microsoft d'entreprise pour
                activer votre accès. L'adresse email associée doit correspondre à
                <span className="font-medium"> {email}</span>.
            </p>

            <a
                href="/auth/microsoft"
                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-[var(--color-border)] text-sm font-medium text-[var(--color-text)] hover:bg-[var(--color-surface-hover)] transition"
            >
                <svg className="w-5 h-5" viewBox="0 0 21 21">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022" />
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00" />
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef" />
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900" />
                </svg>
                Continuer avec Microsoft
            </a>
        </GuestLayout>
    );
}
