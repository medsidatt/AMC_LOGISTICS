import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import FormInput from '@/components/ui/FormInput';
import Button from '@/components/ui/Button';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <GuestLayout title="Connectez-vous à votre compte">
            <Head title="Connexion" />
            <form onSubmit={submit}>
                <FormInput
                    label="Email"
                    type="email"
                    name="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                    autoFocus
                />
                <FormInput
                    label="Mot de passe"
                    type="password"
                    name="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required
                />
                <div className="flex items-center justify-between mb-6">
                    <label className="flex items-center gap-2 text-sm text-[var(--color-text-secondary)] cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-[var(--color-border)]"
                        />
                        Se souvenir
                    </label>
                    <a href="/password/email" className="text-sm text-[var(--color-primary)] hover:underline">
                        Mot de passe oublié ?
                    </a>
                </div>
                <Button type="submit" loading={processing} className="w-full">
                    Connexion
                </Button>
            </form>

            <div className="mt-6">
                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <div className="w-full border-t border-[var(--color-border)]" />
                    </div>
                    <div className="relative flex justify-center text-xs">
                        <span className="px-2 bg-[var(--color-surface)] text-[var(--color-text-muted)]">ou</span>
                    </div>
                </div>
                <a
                    href="/auth/microsoft"
                    className="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-[var(--color-border)] text-sm font-medium text-[var(--color-text)] hover:bg-[var(--color-surface-hover)] transition"
                >
                    <svg className="w-5 h-5" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                    Connexion avec Microsoft
                </a>
            </div>
        </GuestLayout>
    );
}
