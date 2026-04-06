import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/layouts/GuestLayout';
import FormInput from '@/components/ui/FormInput';
import Button from '@/components/ui/Button';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/password/reset');
    };

    return (
        <GuestLayout title="Nouveau mot de passe">
            <Head title="Réinitialiser le mot de passe" />
            <form onSubmit={submit}>
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
                    label="Nouveau mot de passe"
                    type="password"
                    name="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required
                    autoFocus
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
                    Réinitialiser
                </Button>
            </form>
        </GuestLayout>
    );
}
