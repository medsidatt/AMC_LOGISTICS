import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import FormInput from '@/components/ui/FormInput';
import Button from '@/components/ui/Button';

interface Props {
    token: string;
    email: string;
}

export default function AcceptInvitation({ token, email }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        name: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/invitation/accept');
    };

    return (
        <GuestLayout title="Accepter l'invitation">
            <Head title="Invitation" />
            <p className="text-sm text-[var(--color-text-secondary)] mb-4">
                Vous avez été invité à rejoindre AMC Logistics. Créez votre compte.
            </p>
            <form onSubmit={submit}>
                <FormInput
                    label="Email"
                    type="email"
                    name="email"
                    value={data.email}
                    error={errors.email}
                    disabled
                />
                <FormInput
                    label="Nom complet"
                    name="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
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
        </GuestLayout>
    );
}
