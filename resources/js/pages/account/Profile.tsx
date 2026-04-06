import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { User, Lock } from 'lucide-react';

interface Props {
    user: { id: number; name: string; email: string };
}

export default function Profile({ user }: Props) {
    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({ old_password: '', password: '', password_confirmation: '' });

    const submitProfile = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.put('/auth/account/update');
    };

    const submitPassword = (e: React.FormEvent) => {
        e.preventDefault();
        passwordForm.put('/auth/password/update', { onSuccess: () => passwordForm.reset() });
    };

    return (
        <AuthenticatedLayout title="Mon compte">
            <Head title="Mon compte" />

            <div className="grid lg:grid-cols-2 gap-6">
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <User size={20} className="text-[var(--color-primary)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Informations personnelles</h3>
                    </div>
                    <form onSubmit={submitProfile}>
                        <FormInput label="Nom" name="name" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} error={profileForm.errors.name} required />
                        <FormInput label="Email" type="email" name="email" value={profileForm.data.email} onChange={(e) => profileForm.setData('email', e.target.value)} error={profileForm.errors.email} required />
                        <div className="pt-4">
                            <Button type="submit" loading={profileForm.processing}>Enregistrer</Button>
                        </div>
                    </form>
                </Card>

                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Lock size={20} className="text-[var(--color-warning)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Changer le mot de passe</h3>
                    </div>
                    <form onSubmit={submitPassword}>
                        <FormInput label="Ancien mot de passe" type="password" name="old_password" value={passwordForm.data.old_password} onChange={(e) => passwordForm.setData('old_password', e.target.value)} error={passwordForm.errors.old_password} required />
                        <FormInput label="Nouveau mot de passe" type="password" name="password" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} error={passwordForm.errors.password} required />
                        <FormInput label="Confirmer le mot de passe" type="password" name="password_confirmation" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} error={passwordForm.errors.password_confirmation} required />
                        <div className="pt-4">
                            <Button type="submit" loading={passwordForm.processing}>Mettre à jour</Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
