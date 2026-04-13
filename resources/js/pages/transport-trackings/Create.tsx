import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import { ArrowLeft, X, FileText, Image, Eye } from 'lucide-react';

interface TruckOption {
    id: number;
    matricule: string;
    last_driver_id: number | null;
    transporter_id: number | null;
}

interface DropdownItem {
    id: number | string;
    name: string;
}

interface Props {
    transporters: DropdownItem[];
    trucks: TruckOption[];
    drivers: DropdownItem[];
    providers: DropdownItem[];
    products: DropdownItem[];
    bases: DropdownItem[];
}

export default function TrackingsCreate({ transporters, trucks, drivers, providers, products, bases }: Props) {
    const form = useForm({
        truck_id: '' as string | number,
        driver_id: '' as string | number,
        transporter_id: '' as string | number,
        provider_id: '' as string | number,
        product: '',
        base: '',
        provider_date: '',
        client_date: '',
        commune_date: '',
        provider_gross_weight: '',
        provider_tare_weight: '',
        provider_net_weight: '',
        client_gross_weight: '',
        client_tare_weight: '',
        client_net_weight: '',
        commune_weight: '',
        files: [] as File[],
    });

    const [fileList, setFileList] = useState<File[]>([]);
    const [previews, setPreviews] = useState<Record<number, string>>({});
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    // Auto-select last driver & transporter when truck changes
    useEffect(() => {
        const selected = trucks.find((t) => t.id === Number(form.data.truck_id));
        if (selected) {
            if (selected.last_driver_id) form.setData('driver_id', selected.last_driver_id);
            if (selected.transporter_id) form.setData('transporter_id', selected.transporter_id);
        }
    }, [form.data.truck_id]);

    const addFiles = (newFiles: FileList | null) => {
        if (!newFiles) return;
        const newArr = Array.from(newFiles);
        const updated = [...fileList, ...newArr];
        setFileList(updated);
        form.setData('files', updated);
        // Generate preview URLs for all files (images + PDFs)
        const newPreviews = { ...previews };
        newArr.forEach((file, i) => {
            newPreviews[fileList.length + i] = URL.createObjectURL(file);
        });
        setPreviews(newPreviews);
    };

    const removeFile = (index: number) => {
        if (previews[index]) URL.revokeObjectURL(previews[index]);
        const updated = fileList.filter((_, i) => i !== index);
        setFileList(updated);
        form.setData('files', updated);
        // Rebuild preview indexes
        const newPreviews: Record<number, string> = {};
        Object.entries(previews).forEach(([k, v]) => {
            const ki = parseInt(k);
            if (ki < index) newPreviews[ki] = v;
            else if (ki > index) newPreviews[ki - 1] = v;
        });
        setPreviews(newPreviews);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/transport_tracking/store', { forceFormData: true });
    };

    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));
    const toOpts = (items: DropdownItem[]) => items.map((i) => ({ value: i.id, label: i.name }));

    const isPdf = (file: File) => file.type === 'application/pdf';

    return (
        <AuthenticatedLayout title="Nouveau transport">
            <Head title="Nouveau transport" />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
            </div>

            <form onSubmit={submit}>
                <div className="grid lg:grid-cols-2 gap-6">
                    <Card>
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Véhicule & Conducteur</h4>
                        <FormSelect label="Camion" options={truckOpts} value={form.data.truck_id} onChange={(v) => form.setData('truck_id', v ?? '')} error={form.errors.truck_id} required />
                        <FormSelect label="Conducteur" options={toOpts(drivers)} value={form.data.driver_id} onChange={(v) => form.setData('driver_id', v ?? '')} error={form.errors.driver_id} required />
                        <FormSelect label="Transporteur" options={toOpts(transporters)} value={form.data.transporter_id} onChange={(v) => form.setData('transporter_id', v ?? '')} error={form.errors.transporter_id} />
                    </Card>

                    <Card>
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Produit & Localisation</h4>
                        <FormSelect label="Produit" options={toOpts(products)} value={form.data.product} onChange={(v) => form.setData('product', String(v ?? ''))} error={form.errors.product} required />
                        <FormSelect label="Base" options={toOpts(bases)} value={form.data.base} onChange={(v) => form.setData('base', String(v ?? ''))} error={form.errors.base} required />
                        <FormSelect label="Fournisseur" options={toOpts(providers)} value={form.data.provider_id} onChange={(v) => form.setData('provider_id', v ?? '')} error={form.errors.provider_id} />
                    </Card>

                    <Card>
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Dates</h4>
                        <FormInput label="Date fournisseur" type="date" name="provider_date" value={form.data.provider_date} onChange={(e) => form.setData('provider_date', e.target.value)} error={form.errors.provider_date} />
                        <FormInput label="Date client" type="date" name="client_date" value={form.data.client_date} onChange={(e) => form.setData('client_date', e.target.value)} error={form.errors.client_date} />
                        <FormInput label="Date commune" type="date" name="commune_date" value={form.data.commune_date} onChange={(e) => form.setData('commune_date', e.target.value)} error={form.errors.commune_date} />
                    </Card>

                    <Card>
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Poids Fournisseur</h4>
                        <FormInput label="Poids brut" type="number" step="0.01" name="provider_gross_weight" value={form.data.provider_gross_weight} onChange={(e) => form.setData('provider_gross_weight', e.target.value)} error={form.errors.provider_gross_weight} />
                        <FormInput label="Tare" type="number" step="0.01" name="provider_tare_weight" value={form.data.provider_tare_weight} onChange={(e) => form.setData('provider_tare_weight', e.target.value)} error={form.errors.provider_tare_weight} />
                        <FormInput label="Poids net" type="number" step="0.01" name="provider_net_weight" value={form.data.provider_net_weight} onChange={(e) => form.setData('provider_net_weight', e.target.value)} error={form.errors.provider_net_weight} />
                    </Card>

                    <Card>
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Poids Client</h4>
                        <FormInput label="Poids brut" type="number" step="0.01" name="client_gross_weight" value={form.data.client_gross_weight} onChange={(e) => form.setData('client_gross_weight', e.target.value)} error={form.errors.client_gross_weight} />
                        <FormInput label="Tare" type="number" step="0.01" name="client_tare_weight" value={form.data.client_tare_weight} onChange={(e) => form.setData('client_tare_weight', e.target.value)} error={form.errors.client_tare_weight} />
                        <FormInput label="Poids net" type="number" step="0.01" name="client_net_weight" value={form.data.client_net_weight} onChange={(e) => form.setData('client_net_weight', e.target.value)} error={form.errors.client_net_weight} />
                    </Card>

                    <Card>
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Commune & Fichiers</h4>
                        <FormInput label="Poids commune" type="number" step="0.01" name="commune_weight" value={form.data.commune_weight} onChange={(e) => form.setData('commune_weight', e.target.value)} error={form.errors.commune_weight} />
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-[var(--color-text)] mb-1">Fichiers</label>
                            <input
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => { addFiles(e.target.files); e.target.value = ''; }}
                                className="block w-full text-sm text-[var(--color-text-secondary)] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[var(--color-primary)]/10 file:text-[var(--color-primary)] hover:file:bg-[var(--color-primary)]/20"
                            />
                            {form.errors.files && <p className="mt-1 text-xs text-[var(--color-danger)]">{form.errors.files}</p>}
                        </div>

                        {fileList.length > 0 && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                {fileList.map((file, i) => {
                                    const isImage = file.type.startsWith('image/');
                                    const url = previews[i];
                                    return (
                                        <div key={i} className="relative group rounded-lg border border-[var(--color-border)] overflow-hidden bg-[var(--color-surface-hover)]">
                                            {isImage && url ? (
                                                <img src={url} alt={file.name} className="w-full h-32 object-cover cursor-pointer" onClick={() => setPreviewUrl(url)} />
                                            ) : (
                                                <a href={url} target="_blank" rel="noreferrer" className="w-full h-32 flex flex-col items-center justify-center hover:bg-[var(--color-surface)] cursor-pointer">
                                                    <FileText size={36} className="text-red-400" />
                                                    <span className="text-xs text-[var(--color-primary)] mt-2">Ouvrir le PDF</span>
                                                </a>
                                            )}
                                            <div className="px-2 py-1.5 flex items-center justify-between">
                                                <div className="min-w-0">
                                                    <p className="text-xs text-[var(--color-text)] truncate">{file.name}</p>
                                                    <p className="text-xs text-[var(--color-text-muted)]">{(file.size / 1024).toFixed(0)} KB</p>
                                                </div>
                                                {isImage && url && (
                                                    <a href={url} target="_blank" rel="noreferrer" className="p-1 text-[var(--color-info)] hover:bg-[var(--color-info)]/10 rounded shrink-0" title="Ouvrir">
                                                        <Eye size={14} />
                                                    </a>
                                                )}
                                            </div>
                                            <button type="button" onClick={() => removeFile(i)}
                                                className="absolute top-1 right-1 p-1 rounded-full bg-red-500 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                                                <X size={12} />
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Image preview modal */}
                        {previewUrl && (
                            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70" onClick={() => setPreviewUrl(null)}>
                                <div className="relative max-w-3xl max-h-[90vh]">
                                    <img src={previewUrl} alt="Preview" className="max-w-full max-h-[90vh] rounded-lg" />
                                    <button onClick={() => setPreviewUrl(null)} className="absolute top-2 right-2 p-2 rounded-full bg-black/50 text-white hover:bg-black/70">
                                        <X size={20} />
                                    </button>
                                </div>
                            </div>
                        )}
                    </Card>
                </div>

                <div className="flex gap-2 mt-6">
                    <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                    <Button type="submit" loading={form.processing}>Créer</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
