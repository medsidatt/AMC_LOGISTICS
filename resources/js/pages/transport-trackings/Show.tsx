import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { ArrowLeft, Pencil, FileText, Image, ExternalLink } from 'lucide-react';

interface Document {
    id: number;
    original_name: string;
    mime_type: string;
    type: string;
    file_url: string;
}

interface Props {
    tracking: {
        id: number;
        reference: string;
        truck: string | null;
        driver: string | null;
        provider: string | null;
        transporter: string | null;
        product: string;
        base: string;
        provider_date: string | null;
        client_date: string | null;
        commune_date: string | null;
        provider_gross_weight: number | null;
        provider_tare_weight: number | null;
        provider_net_weight: number | null;
        client_gross_weight: number | null;
        client_tare_weight: number | null;
        client_net_weight: number | null;
        commune_weight: number | null;
        gap: number | null;
        documents: Document[];
    };
}

export default function TrackingsShow({ tracking: t }: Props) {
    const fmt = (v: number | null) => v != null ? v.toLocaleString('fr-FR') : '-';
    const isPdf = (mime: string) => mime === 'application/pdf';

    return (
        <AuthenticatedLayout title={t.reference}>
            <Head title={t.reference} />

            <div className="flex items-center justify-between mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
                <Button variant="secondary" icon={<Pencil size={16} />} onClick={() => window.location.href = `/transport_tracking/${t.id}/edit-page`}>Modifier</Button>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                <Card>
                    <h4 className="font-semibold text-[var(--color-text)] mb-4">Informations générales</h4>
                    <div className="grid sm:grid-cols-2 gap-4">
                        {[
                            ['Référence', t.reference],
                            ['Camion', t.truck],
                            ['Conducteur', t.driver],
                            ['Fournisseur', t.provider],
                            ['Transporteur', t.transporter],
                            ['Produit', t.product],
                            ['Base', t.base],
                        ].map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                                <p className="text-sm text-[var(--color-text)] mt-0.5">{value || '-'}</p>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card>
                    <h4 className="font-semibold text-[var(--color-text)] mb-4">Dates</h4>
                    <div className="grid sm:grid-cols-3 gap-4">
                        {[
                            ['Date fournisseur', t.provider_date],
                            ['Date client', t.client_date],
                            ['Date commune', t.commune_date],
                        ].map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                                <p className="text-sm text-[var(--color-text)] mt-0.5">{value || '-'}</p>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card>
                    <h4 className="font-semibold text-[var(--color-text)] mb-4">Poids Fournisseur</h4>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Brut</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{fmt(t.provider_gross_weight)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Tare</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{fmt(t.provider_tare_weight)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Net</p>
                            <p className="text-sm font-semibold text-[var(--color-text)] mt-0.5">{fmt(t.provider_net_weight)}</p>
                        </div>
                    </div>
                </Card>

                <Card>
                    <h4 className="font-semibold text-[var(--color-text)] mb-4">Poids Client</h4>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Brut</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{fmt(t.client_gross_weight)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Tare</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{fmt(t.client_tare_weight)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Net</p>
                            <p className="text-sm font-semibold text-[var(--color-text)] mt-0.5">{fmt(t.client_net_weight)}</p>
                        </div>
                    </div>
                    <div className="mt-4 pt-4 border-t border-[var(--color-border)] flex justify-between items-center">
                        <span className="text-sm text-[var(--color-text-secondary)]">{(t.gap ?? 0) < 0 ? 'Perte' : (t.gap ?? 0) > 0 ? 'Excédent' : 'Écart'}</span>
                        {(t.gap ?? 0) < 0
                            ? <Badge variant="danger">Perte {fmt(Math.abs(t.gap ?? 0))} kg</Badge>
                            : (t.gap ?? 0) > 0
                                ? <Badge variant="info">Exc. +{fmt(t.gap)} kg</Badge>
                                : <Badge variant="success">OK</Badge>
                        }
                    </div>
                </Card>

                {t.documents.length > 0 && (
                    <Card className="lg:col-span-2">
                        <h4 className="font-semibold text-[var(--color-text)] mb-4">Documents ({t.documents.length})</h4>
                        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {t.documents.map((doc) => (
                                <a key={doc.id} href={doc.file_url} target="_blank" rel="noreferrer"
                                   className="flex items-center gap-3 rounded-lg border border-[var(--color-border)] px-4 py-3 hover:bg-[var(--color-surface-hover)] transition-colors group">
                                    {isPdf(doc.mime_type)
                                        ? <FileText size={20} className="text-red-500 shrink-0" />
                                        : <Image size={20} className="text-blue-500 shrink-0" />
                                    }
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-[var(--color-text)] truncate">{doc.original_name}</p>
                                        <p className="text-xs text-[var(--color-text-muted)]">{doc.type}</p>
                                    </div>
                                    <ExternalLink size={14} className="text-[var(--color-text-muted)] shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </a>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
