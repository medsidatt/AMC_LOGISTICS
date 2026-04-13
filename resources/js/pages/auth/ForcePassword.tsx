import { Head, useForm } from '@inertiajs/react';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { Lock, ShieldAlert } from 'lucide-react';

export default function ForcePassword() {
    const form = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/auth/force-password/update');
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-[var(--color-bg)] px-4">
            <Head title="Changer le mot de passe" />

            <div className="w-full max-w-md">
                <div className="bg-[var(--color-surface)] rounded-2xl border border-[var(--color-border)] shadow-lg p-8">
                    <div className="text-center mb-6">
                        <div className="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/20 flex items-center justify-center mx-auto mb-4">
                            <ShieldAlert size={32} className="text-amber-500" />
                        </div>
                        <h1 className="text-xl font-bold text-[var(--color-text)]">Changement de mot de passe requis</h1>
                        <p className="text-sm text-[var(--color-text-muted)] mt-2">
                            Votre compte a été créé par un administrateur. Pour des raisons de sécurité, veuillez définir votre propre mot de passe.
                        </p>
                    </div>

                    <form onSubmit={submit}>
                        <FormInput
                            label="Nouveau mot de passe"
                            type="password"
                            name="password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            error={form.errors.password}
                            required
                            autoFocus
                        />
                        <FormInput
                            label="Confirmer le mot de passe"
                            type="password"
                            name="password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            error={form.errors.password_confirmation}
                            required
                        />

                        <div className="mt-2 p-3 rounded-lg bg-[var(--color-surface-hover)] text-xs text-[var(--color-text-muted)]">
                            <div className="flex items-center gap-2">
                                <Lock size={14} />
                                <span>Le mot de passe doit contenir au moins 8 caractères</span>
                            </div>
                        </div>

                        <Button type="submit" loading={form.processing} className="w-full mt-6">
                            Définir mon mot de passe
                        </Button>
                    </form>
                </div>

                <p className="text-center text-xs text-[var(--color-text-muted)] mt-4">
                    AMC Logistics
                </p>
            </div>
        </div>
    );
}
