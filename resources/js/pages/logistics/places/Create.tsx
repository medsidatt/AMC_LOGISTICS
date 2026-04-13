import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import LeafletMap from '@/components/map/LeafletMap';
import { ArrowLeft } from 'lucide-react';

interface Provider {
    id: number;
    name: string;
}

interface Props {
    providers: Provider[];
}

const TYPE_OPTIONS: Array<[string, string]> = [
    ['base', 'Base'],
    ['provider_site', 'Site fournisseur'],
    ['client_site', 'Site client'],
    ['fuel_station', 'Station-service'],
    ['parking', 'Parking'],
    ['unknown', 'Autre'],
];

export default function PlacesCreate({ providers }: Props) {
    const form = useForm({
        code: '',
        name: '',
        type: 'base',
        latitude: 16.0 as number | string,
        longitude: -16.5 as number | string,
        radius_m: 300 as number,
        provider_id: '' as number | '',
        is_active: true,
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/logistics/places');
    };

    const lat = Number(form.data.latitude);
    const lng = Number(form.data.longitude);
    const hasValidCoords = Number.isFinite(lat) && Number.isFinite(lng);

    return (
        <AuthenticatedLayout title="Nouveau lieu">
            <Head title="Nouveau lieu" />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={14} />} onClick={() => router.visit('/logistics/places')}>
                    Retour
                </Button>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <Card header="Informations">
                        <div className="space-y-4">
                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Nom *</label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                    required
                                />
                                {form.errors.name && <p className="text-xs text-red-500 mt-1">{form.errors.name}</p>}
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Code (unique, optionnel)</label>
                                <input
                                    type="text"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value)}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                    placeholder="ex: base_mr, station_dakar"
                                />
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Type *</label>
                                <select
                                    value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                >
                                    {TYPE_OPTIONS.map(([val, label]) => (
                                        <option key={val} value={val}>{label}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Fournisseur lié (optionnel)</label>
                                <select
                                    value={form.data.provider_id}
                                    onChange={(e) => form.setData('provider_id', e.target.value === '' ? '' : Number(e.target.value))}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                >
                                    <option value="">—</option>
                                    {providers.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Notes</label>
                                <textarea
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                    rows={3}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                />
                                <label htmlFor="is_active" className="text-sm text-[var(--color-text)]">Actif (géofence actif)</label>
                            </div>
                        </div>
                    </Card>

                    <Card header="Position">
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Latitude *</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={form.data.latitude}
                                        onChange={(e) => form.setData('latitude', e.target.value)}
                                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Longitude *</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={form.data.longitude}
                                        onChange={(e) => form.setData('longitude', e.target.value)}
                                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Rayon du géofence (m) *</label>
                                <input
                                    type="number"
                                    min={50}
                                    max={5000}
                                    value={form.data.radius_m}
                                    onChange={(e) => form.setData('radius_m', Number(e.target.value))}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                    required
                                />
                                <p className="text-xs text-[var(--color-text-muted)] mt-1">
                                    Un arrêt dans ce rayon sera classé comme "connu".
                                </p>
                            </div>

                            {hasValidCoords && (
                                <div className="mt-3">
                                    <LeafletMap
                                        height={240}
                                        markers={[{
                                            id: 'preview',
                                            latitude: lat,
                                            longitude: lng,
                                            color: '#10b981',
                                        }]}
                                        circles={[{
                                            id: 'preview-circle',
                                            latitude: lat,
                                            longitude: lng,
                                            radiusMeters: Number(form.data.radius_m),
                                            color: '#10b981',
                                        }]}
                                    />
                                </div>
                            )}
                        </div>
                    </Card>
                </div>

                <div className="flex justify-end gap-2 mt-5">
                    <Button variant="secondary" type="button" onClick={() => router.visit('/logistics/places')}>
                        Annuler
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        Créer
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
