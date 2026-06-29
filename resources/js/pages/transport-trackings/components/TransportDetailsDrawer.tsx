import { useCallback, useEffect, useState } from 'react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Tabs from '@/components/ui/Tabs';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import DocumentManager, { type TrackingDocument } from './DocumentManager';
import AiAnalysisPanel from './AiAnalysisPanel';
import { apiFetch } from '@/utils/csrf';
import { Package, Pencil, Loader2, FileText, Sparkles, Info } from 'lucide-react';

interface TrackingDetail {
    id: number;
    reference: string;
    truck: string | null;
    driver: string | null;
    provider: string | null;
    transporter: string | null;
    product: string | null;
    base: string | null;
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
    documents: TrackingDocument[];
}

interface Props {
    id: number;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

const fmt = (v: number | null | undefined) => (v == null ? '—' : v.toLocaleString('fr-FR', { maximumFractionDigits: 2 }));

/**
 * Tabbed details drawer (Détails / Documents / Analyse IA) — the standard
 * read-and-act surface for a record. Fetches the full record as JSON on open;
 * documents upload/delete in-place; AI runs synchronously in its tab. One drawer,
 * no stacking.
 */
export default function TransportDetailsDrawer({ id, canEdit, onEdit, onClose }: Props) {
    const [tab, setTab] = useState('details');
    const [data, setData] = useState<TrackingDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await apiFetch(`/transport_tracking/${id}/show-page`);
            if (res.ok) { const j = await res.json(); setData(j.tracking); }
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    const upload = async (files: FileList) => {
        setUploading(true);
        try {
            const fd = new FormData();
            Array.from(files).forEach((f) => fd.append('files[]', f));
            const res = await apiFetch(`/transport_tracking/${id}/documents`, { method: 'POST', body: fd });
            if (res.ok) await load();
        } finally {
            setUploading(false);
        }
    };

    const del = async (docId: number) => {
        if (!confirm('Supprimer ce document ?')) return;
        setDeletingId(docId);
        try {
            const res = await apiFetch(`/transport_tracking/${id}/document/${docId}`, { method: 'DELETE' });
            if (res.ok) setData((d) => (d ? { ...d, documents: d.documents.filter((x) => x.id !== docId) } : d));
        } finally {
            setDeletingId(null);
        }
    };

    const gapBadge = data && data.gap != null
        ? (data.gap < 0 ? <Badge variant="danger">{fmt(data.gap)}</Badge> : data.gap > 0 ? <Badge variant="info">+{fmt(data.gap)}</Badge> : <Badge variant="success">0</Badge>)
        : '—';

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<Package size={18} className="text-[var(--color-primary)]" />}
            title={data?.reference ?? 'Transport'}
            footer={canEdit ? <Button icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button> : undefined}
        >
            {loading ? (
                <div className="flex flex-col items-center justify-center py-16">
                    <Loader2 size={28} className="animate-spin text-[var(--color-primary)]" />
                    <p className="text-sm text-[var(--color-text-muted)] mt-3">Chargement…</p>
                </div>
            ) : !data ? (
                <p className="text-sm text-[var(--color-text-muted)] text-center py-10">Transport introuvable.</p>
            ) : (
                <>
                    <Tabs
                        active={tab}
                        onChange={setTab}
                        tabs={[
                            { key: 'details', label: 'Détails', icon: <Info size={15} /> },
                            { key: 'documents', label: `Documents${data.documents.length ? ` (${data.documents.length})` : ''}`, icon: <FileText size={15} /> },
                            { key: 'ia', label: 'Analyse IA', icon: <Sparkles size={15} /> },
                        ]}
                    />
                    <div className="pt-4">
                        {tab === 'details' && (
                            <div className="space-y-4">
                                <DetailPanel columns={2}>
                                    <DetailItem label="Camion" value={data.truck} />
                                    <DetailItem label="Conducteur" value={data.driver} />
                                    <DetailItem label="Fournisseur" value={data.provider} />
                                    <DetailItem label="Transporteur" value={data.transporter} />
                                    <DetailItem label="Produit" value={data.product} />
                                    <DetailItem label="Base" value={data.base} />
                                    <DetailItem label="Date fournisseur" value={data.provider_date} />
                                    <DetailItem label="Date client" value={data.client_date} />
                                </DetailPanel>
                                <DetailPanel columns={3}>
                                    <DetailItem label="Fourn. brut" value={fmt(data.provider_gross_weight)} />
                                    <DetailItem label="Fourn. tare" value={fmt(data.provider_tare_weight)} />
                                    <DetailItem label="Fourn. net" value={fmt(data.provider_net_weight)} />
                                    <DetailItem label="Client brut" value={fmt(data.client_gross_weight)} />
                                    <DetailItem label="Client tare" value={fmt(data.client_tare_weight)} />
                                    <DetailItem label="Client net" value={fmt(data.client_net_weight)} />
                                </DetailPanel>
                                <DetailPanel columns={2}>
                                    <DetailItem label="Poids commune" value={fmt(data.commune_weight)} />
                                    <DetailItem label="Perte / Excédent" value={gapBadge} />
                                </DetailPanel>
                            </div>
                        )}
                        {tab === 'documents' && (
                            <DocumentManager
                                existing={data.documents}
                                onDeleteExisting={del}
                                deletingId={deletingId}
                                onAddFiles={upload}
                                uploading={uploading}
                                emptyHint="Aucun document attaché à ce transport."
                            />
                        )}
                        {tab === 'ia' && (
                            <AiAnalysisPanel defaultQuestion={`Analyse les écarts de poids et anomalies pour le transport ${data.reference}.`} />
                        )}
                    </div>
                </>
            )}
        </Drawer>
    );
}
