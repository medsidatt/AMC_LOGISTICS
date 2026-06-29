import { useState } from 'react';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import { apiFetch } from '@/utils/csrf';
import { Sparkles, Loader2, AlertTriangle } from 'lucide-react';

interface Props {
    /** Pre-fills the question with context about the current record. */
    defaultQuestion?: string;
}

/**
 * AI analysis tab — calls the EXISTING synchronous /ask-ai endpoint (business
 * logic unchanged) and renders the result with a professional loading state,
 * without leaving the workspace. The async redesign (queues/polling/persistence)
 * is P1.5, out of scope here.
 */
export default function AiAnalysisPanel({ defaultQuestion = '' }: Props) {
    const [question, setQuestion] = useState(defaultQuestion);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const run = async () => {
        if (!question.trim() || loading) return;
        setLoading(true);
        setError(null);
        setResult(null);
        try {
            const res = await apiFetch('/transport_tracking/ask-ai', {
                method: 'POST',
                body: JSON.stringify({ question }),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            setResult(typeof json.data === 'string' ? json.data : JSON.stringify(json.data, null, 2));
        } catch (e) {
            setError("L'analyse a échoué. Réessayez dans un instant.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Question</label>
                <textarea
                    value={question}
                    onChange={(e) => setQuestion(e.target.value)}
                    rows={3}
                    placeholder="Ex : analyse les écarts de poids et les anomalies pour ce transport."
                    className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                />
            </div>
            <Button icon={<Sparkles size={15} />} onClick={run} disabled={!question.trim() || loading} loading={loading}>
                Analyser
            </Button>

            {loading && (
                <div className="flex flex-col items-center justify-center py-10 text-center">
                    <Loader2 size={28} className="animate-spin text-[var(--color-primary)]" />
                    <p className="text-sm text-[var(--color-text)] mt-3">Analyse en cours…</p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">L'IA examine les données de transport.</p>
                </div>
            )}

            {error && !loading && (
                <div className="flex items-center gap-2 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-3 py-2 text-sm text-[var(--color-danger)]">
                    <AlertTriangle size={16} /> {error}
                </div>
            )}

            {result && !loading && (
                <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)] p-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2">Résultat</p>
                    <div className="text-sm text-[var(--color-text)] whitespace-pre-wrap leading-relaxed max-h-[50vh] overflow-y-auto">{result}</div>
                </div>
            )}

            {!result && !loading && !error && (
                <EmptyState icon={<Sparkles size={28} />} title="Analyse IA" description="Posez une question pour analyser les données de transport." />
            )}
        </div>
    );
}
