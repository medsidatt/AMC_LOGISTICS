import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import FormInput from '@/components/ui/FormInput';
import Button from '@/components/ui/Button';

interface Props {
    status?: string;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/password/email');
    };

    return (
        <GuestLayout title="Réinitialiser le mot de passe">
            <Head title="Mot de passe oublié" />
            {status && (
                <div className="mb-4 p-3 rounded-lg bg-emerald-500/10 text-sm text-emerald-600">{status}</div>
            )}
            <p className="text-sm text-[var(--color-text-secondary)] mb-4">
                Entrez votre email et nous vous enverrons un lien de réinitialisation.
            </p>
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
                <Button type="submit" loading={processing} className="w-full">
                    Envoyer le lien
                </Button>
            </form>
            <p className="text-center text-sm text-[var(--color-text-muted)] mt-4">
                <a href="/login" className="text-[var(--color-primary)] hover:underline">Retour à la connexion</a>
            </p>
        </GuestLayout>
    );
}
