import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import LeafletMap from '@/components/map/LeafletMap';
import { ArrowLeft } from 'lucide-react';

interface Provider {
    id: number;
    name: string;
}

interface Place {
    id: number;
    code: string | null;
    name: string;
    type: string;
    latitude: number;
    longitude: number;
    radius_m: number;
    provider_id: number | null;
    is_active: boolean;
    is_auto_detected: boolean;
    notes: string | null;
}

interface Props {
    place: Place;
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

export default function PlacesEdit({ place, providers }: Props) {
    const form = useForm({
        code: place.code ?? '',
        name: place.name,
        type: place.type,
        latitude: place.latitude as number | string,
        longitude: place.longitude as number | string,
        radius_m: place.radius_m,
        provider_id: (place.provider_id ?? '') as number | '',
        is_active: place.is_active,
        notes: place.notes ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/logistics/places/${place.id}`);
    };

    const lat = Number(form.data.latitude);
    const lng = Number(form.data.longitude);
    const coordinatesLocked = place.is_auto_detected;

    return (
        <AuthenticatedLayout title={`Modifier: ${place.name}`}>
            <Head title={`Modifier ${place.name}`} />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={14} />} onClick={() => router.visit('/logistics/places')}>
                    Retour
                </Button>
            </div>

            {place.is_auto_detected && (
                <div className="mb-4 rounded-lg border border-blue-300 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 px-4 py-3 text-sm text-blue-800 dark:text-blue-300 flex items-center gap-2">
                    <Badge variant="info">Auto</Badge>
                    Ce lieu est auto-détecté depuis la télémétrie. Les coordonnées sont rafraîchies chaque nuit et ne sont pas éditables ici.
                </div>
            )}

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
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Code</label>
                                <input
                                    type="text"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value)}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
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
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Fournisseur lié</label>
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
                                    <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Latitude</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={form.data.latitude}
                                        onChange={(e) => form.setData('latitude', e.target.value)}
                                        disabled={coordinatesLocked}
                                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm disabled:opacity-50"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Longitude</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={form.data.longitude}
                                        onChange={(e) => form.setData('longitude', e.target.value)}
                                        disabled={coordinatesLocked}
                                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm disabled:opacity-50"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="text-xs text-[var(--color-text-muted)] uppercase mb-1 block">Rayon (m)</label>
                                <input
                                    type="number"
                                    min={50}
                                    max={5000}
                                    value={form.data.radius_m}
                                    onChange={(e) => form.setData('radius_m', Number(e.target.value))}
                                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                />
                            </div>

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
                        </div>
                    </Card>
                </div>

                <div className="flex justify-end gap-2 mt-5">
                    <Button variant="secondary" type="button" onClick={() => router.visit('/logistics/places')}>
                        Annuler
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        Enregistrer
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
