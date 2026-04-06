import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import FormInput from '@/components/ui/FormInput';
import Button from '@/components/ui/Button';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <GuestLayout title="Créer un compte">
            <Head title="Inscription" />
            <form onSubmit={submit}>
                <FormInput
                    label="Nom"
                    name="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    required
                    autoFocus
                />
                <FormInput
                    label="Email"
                    type="email"
                    name="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
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
                <FormInput
                    label="Confirmer le mot de passe"
                    type="password"
                    name="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    required
                />
                <Button type="submit" loading={processing} className="w-full mt-2">
                    Créer mon compte
                </Button>
            </form>
            <p className="text-center text-sm text-[var(--color-text-muted)] mt-4">
                Déjà inscrit ? <a href="/login" className="text-[var(--color-primary)] hover:underline">Connexion</a>
            </p>
        </GuestLayout>
    );
}
