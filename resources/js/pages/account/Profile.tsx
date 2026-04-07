import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import { User, Lock, Mail, ShieldCheck } from 'lucide-react';

interface Props {
    user: { id: number; name: string; email: string };
}

export default function Profile({ user }: Props) {
    const { auth } = usePage().props;
    const profileForm = useForm({ name: user.name });
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

            {/* Profile header */}
            <Card className="mb-6">
                <div className="flex flex-col sm:flex-row items-center gap-4">
                    <div className="w-20 h-20 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-3xl font-bold shrink-0">
                        {user.name?.charAt(0).toUpperCase()}
                    </div>
                    <div className="text-center sm:text-left flex-1">
                        <h2 className="text-xl font-bold text-[var(--color-text)]">{user.name}</h2>
                        <div className="flex items-center justify-center sm:justify-start gap-2 mt-1 text-sm text-[var(--color-text-secondary)]">
                            <Mail size={14} />
                            <span>{user.email}</span>
                        </div>
                        <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-2">
                            {auth.roles?.map((role: string) => (
                                <Badge key={role} variant="primary">{role}</Badge>
                            ))}
                        </div>
                    </div>
                </div>
            </Card>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Personal info */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <div className="p-2 rounded-lg bg-[var(--color-primary)]/10">
                            <User size={18} className="text-[var(--color-primary)]" />
                        </div>
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Informations personnelles</h3>
                    </div>
                    <form onSubmit={submitProfile} className="space-y-1">
                        <FormInput label="Nom complet" name="name" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} error={profileForm.errors.name} required />
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-[var(--color-text)] mb-1">Adresse email</label>
                            <p className="px-3 py-2 rounded-lg bg-[var(--color-surface-hover)] text-sm text-[var(--color-text-secondary)]">{user.email}</p>
                        </div>
                        <div className="pt-3">
                            <Button type="submit" loading={profileForm.processing}>Enregistrer</Button>
                        </div>
                    </form>
                </Card>

                {/* Password */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <div className="p-2 rounded-lg bg-amber-500/10">
                            <Lock size={18} className="text-amber-500" />
                        </div>
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Changer le mot de passe</h3>
                    </div>
                    <form onSubmit={submitPassword} className="space-y-1">
                        <FormInput label="Mot de passe actuel" type="password" name="old_password" value={passwordForm.data.old_password} onChange={(e) => passwordForm.setData('old_password', e.target.value)} error={passwordForm.errors.old_password} required />
                        <FormInput label="Nouveau mot de passe" type="password" name="password" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} error={passwordForm.errors.password} required />
                        <FormInput label="Confirmer" type="password" name="password_confirmation" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} error={passwordForm.errors.password_confirmation} required />
                        <div className="pt-3">
                            <Button type="submit" loading={passwordForm.processing}>Mettre à jour</Button>
                        </div>
                    </form>
                </Card>

                {/* Account info (read-only) */}
                <Card className="lg:col-span-2">
                    <div className="flex items-center gap-2 mb-4">
                        <div className="p-2 rounded-lg bg-emerald-500/10">
                            <ShieldCheck size={18} className="text-emerald-500" />
                        </div>
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Informations du compte</h3>
                    </div>
                    <div className="grid sm:grid-cols-3 gap-4">
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">ID</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5 font-mono">#{user.id}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Rôles</p>
                            <div className="flex flex-wrap gap-1 mt-1">
                                {auth.roles?.map((role: string) => (
                                    <Badge key={role} variant="primary">{role}</Badge>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Permissions</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{auth.permissions?.length ?? 0} permissions</p>
                        </div>
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
