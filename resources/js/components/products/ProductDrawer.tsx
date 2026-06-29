import { useState } from 'react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import { apiFetch } from '@/utils/csrf';
import { Package } from 'lucide-react';

export interface CatalogProduct {
    id: number;
    name: string;
    reference?: string | null;
    category?: string | null;
    unit?: string | null;
}

interface Props {
    initialName?: string;
    categories?: { value: string; label: string }[];
    units?: { value: string; label: string }[];
    onCreated: (product: CatalogProduct) => void;
    onClose: () => void;
}

/**
 * Inline creation of a Product Catalog entry. Posts JSON (idempotent server-side),
 * returns the product to the caller, and never navigates — the parent form that
 * opened it stays mounted. Shared by every product selector.
 */
export default function ProductDrawer({ initialName = '', categories, units, onCreated, onClose }: Props) {
    const [name, setName] = useState(initialName);
    const [reference, setReference] = useState('');
    const [category, setCategory] = useState('');
    const [unit, setUnit] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const save = async () => {
        if (!name.trim() || saving) return;
        setSaving(true);
        setError(null);
        try {
            const res = await apiFetch('/products', { method: 'POST', body: JSON.stringify({ name: name.trim(), reference: reference || null, category: category || null, unit: unit || null }) });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json?.product) { setError(json?.message ?? 'Échec de la création du produit.'); return; }
            onCreated(json.product);
        } catch {
            setError('Erreur réseau.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Drawer
            open
            onClose={onClose}
            icon={<Package size={18} className="text-[var(--color-primary)]" />}
            title="Nouveau produit"
            footer={<FormActions onCancel={onClose} onSubmit={save} submitLabel="Créer le produit" loading={saving} disabled={!name.trim()} />}
        >
            <FormInput label="Nom du produit" value={name} onChange={(e) => setName(e.target.value)} required autoFocus />
            <FormInput label="Référence (optionnel)" value={reference} onChange={(e) => setReference(e.target.value)} />
            {categories && <FormSelect label="Catégorie" placeholder="—" options={categories} value={category || null} onChange={(v) => setCategory(String(v ?? ''))} />}
            {units && <FormSelect label="Unité" placeholder="—" options={units} value={unit || null} onChange={(v) => setUnit(String(v ?? ''))} />}
            {error && <p className="text-xs text-[var(--color-danger)]">{error}</p>}
            <p className="text-xs text-[var(--color-text-muted)]">Le produit est ajouté au catalogue partagé et réutilisable par tous les modules.</p>
        </Drawer>
    );
}
