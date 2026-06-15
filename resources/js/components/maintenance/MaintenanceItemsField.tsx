import { Plus, Trash2, Paperclip, FileText } from 'lucide-react';
import SectionTitle from '@/components/ui/SectionTitle';

export interface LineItem {
    designation: string;
    reference: string;
    category: string;
    quantity: string;
    unit: string;
    unit_price: string;
}

export function blankLineItem(): LineItem {
    return { designation: '', reference: '', category: 'piece', quantity: '1', unit: 'piece', unit_price: '' };
}

/** Sensible default unit for a category (oil is billed in litres). */
const defaultUnitFor = (category: string) => (category === 'huile' ? 'litre' : 'piece');

const fcfa = (n: number) => `${Math.round(n).toLocaleString('fr-FR')} FCFA`;

interface Props {
    items: LineItem[];
    onChange: (items: LineItem[]) => void;
    categories: Record<string, string>;
    units: Record<string, string>;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
    /** Facture document upload (optional) */
    facture?: File | null;
    onFactureChange?: (file: File | null) => void;
    factureUrl?: string | null;
    factureName?: string | null;
    factureError?: string;
}

export default function MaintenanceItemsField({
    items, onChange, categories, units, errors, disabled,
    facture, onFactureChange, factureUrl, factureName, factureError,
}: Props) {
    const catKeys = Object.keys(categories);
    const unitKeys = Object.keys(units);

    const update = (idx: number, patch: Partial<LineItem>) =>
        onChange(items.map((it, i) => (i === idx ? { ...it, ...patch } : it)));

    const remove = (idx: number) => onChange(items.filter((_, i) => i !== idx));
    const add = () => onChange([...items, blankLineItem()]);

    const lineTotal = (it: LineItem) => (Number(it.quantity) || 0) * (Number(it.unit_price) || 0);

    const totalsByCategory = catKeys
        .map((key) => ({
            key,
            label: categories[key],
            total: items.filter((it) => it.category === key).reduce((s, it) => s + lineTotal(it), 0),
        }))
        .filter((t) => t.total > 0);

    const grandTotal = items.reduce((s, it) => s + lineTotal(it), 0);

    const inputCls =
        'w-full px-2 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]';

    return (
        <div className="space-y-3">
            <SectionTitle>Facture — pièces, huile &amp; main d'œuvre</SectionTitle>

            {items.length === 0 ? (
                <p className="text-sm text-[var(--color-text-muted)]">Aucune ligne. Ajoutez les pièces et prestations facturées.</p>
            ) : (
                <div className="space-y-2">
                    {/* Column header (desktop) */}
                    <div className="hidden md:grid grid-cols-[1.7fr_1.1fr_0.7fr_0.9fr_1fr_1fr_auto] gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">
                        <span>Désignation</span>
                        <span>Catégorie</span>
                        <span className="text-right">Qté</span>
                        <span>Unité</span>
                        <span className="text-right">Prix U.</span>
                        <span className="text-right">Total</span>
                        <span />
                    </div>

                    {items.map((it, idx) => (
                        <div
                            key={idx}
                            className="grid grid-cols-2 md:grid-cols-[1.7fr_1.1fr_0.7fr_0.9fr_1fr_1fr_auto] gap-2 items-center rounded-lg border border-[var(--color-border)] md:border-0 p-2 md:p-0"
                        >
                            <div className="col-span-2 md:col-span-1">
                                <input
                                    type="text"
                                    className={inputCls}
                                    placeholder="Désignation"
                                    value={it.designation}
                                    disabled={disabled}
                                    onChange={(e) => update(idx, { designation: e.target.value })}
                                />
                                {errors?.[`items.${idx}.designation`] && (
                                    <p className="mt-0.5 text-xs text-red-600">{errors[`items.${idx}.designation`]}</p>
                                )}
                            </div>

                            <select
                                className={inputCls}
                                value={it.category}
                                disabled={disabled}
                                onChange={(e) => update(idx, { category: e.target.value, unit: defaultUnitFor(e.target.value) })}
                            >
                                {catKeys.map((key) => (
                                    <option key={key} value={key}>
                                        {categories[key]}
                                    </option>
                                ))}
                            </select>

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className={`${inputCls} text-right text-base font-semibold`}
                                placeholder="Qté"
                                value={it.quantity}
                                disabled={disabled}
                                onChange={(e) => update(idx, { quantity: e.target.value })}
                            />

                            <select
                                className={inputCls}
                                value={it.unit}
                                disabled={disabled}
                                onChange={(e) => update(idx, { unit: e.target.value })}
                            >
                                {unitKeys.map((key) => (
                                    <option key={key} value={key}>
                                        {units[key]}
                                    </option>
                                ))}
                            </select>

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className={`${inputCls} text-right`}
                                placeholder="Prix U."
                                value={it.unit_price}
                                disabled={disabled}
                                onChange={(e) => update(idx, { unit_price: e.target.value })}
                            />

                            <div className="flex items-center justify-end h-[34px] font-mono text-sm font-semibold text-[var(--color-text)] whitespace-nowrap">
                                {Math.round(lineTotal(it)).toLocaleString('fr-FR')}
                            </div>

                            <button
                                type="button"
                                onClick={() => remove(idx)}
                                disabled={disabled}
                                title="Supprimer la ligne"
                                className="flex items-center justify-center h-[34px] w-8 rounded-md text-red-500 hover:bg-red-50 disabled:opacity-40"
                            >
                                <Trash2 size={16} />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <button
                type="button"
                onClick={add}
                disabled={disabled}
                className="inline-flex items-center gap-1 text-sm font-medium text-[var(--color-primary)] hover:underline disabled:opacity-40"
            >
                <Plus size={14} /> Ajouter une ligne
            </button>

            {grandTotal > 0 && (
                <div className="mt-2 border-t border-[var(--color-border)] pt-2 space-y-0.5 text-sm">
                    {totalsByCategory.map((t) => (
                        <div key={t.key} className="flex justify-between text-[var(--color-text-secondary)]">
                            <span>Total {t.label}</span>
                            <span className="font-mono">{fcfa(t.total)}</span>
                        </div>
                    ))}
                    <div className="flex justify-between font-semibold text-[var(--color-text)] pt-1">
                        <span>Total général</span>
                        <span className="font-mono">{fcfa(grandTotal)}</span>
                    </div>
                </div>
            )}

            {onFactureChange && (
                <div className="border-t border-[var(--color-border)] pt-3">
                    <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-1 flex items-center gap-1">
                        <Paperclip size={14} /> Joindre la facture (PDF ou image)
                    </label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        disabled={disabled}
                        onChange={(e) => onFactureChange(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-surface-hover)] file:text-[var(--color-text-secondary)]"
                    />
                    {factureError && <p className="mt-1 text-xs text-red-600">{factureError}</p>}
                    {factureUrl && !facture && (
                        <a href={factureUrl} target="_blank" rel="noopener noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline">
                            <FileText size={12} /> Facture actuelle{factureName ? ` — ${factureName}` : ''}
                        </a>
                    )}
                </div>
            )}
        </div>
    );
}
