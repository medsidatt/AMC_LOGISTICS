import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { ConclusionCard, type Conclusion } from '@/components/command-center/ConclusionCard';

/**
 * R2.1 — Executive Command Center, minimal end-to-end proof.
 *
 * This page only DISPLAYS the presentation-ready response produced by the Executive
 * Command Center. It contains no business logic, no calculation, no charts — it exists to
 * prove the architecture flows through to React. UI/design is deliberately out of scope.
 */

interface Summary {
    total: number;
    immediate: number;
    bySeverity: Record<string, number>;
    byOwner: Record<string, number>;
}

interface Props {
    version: number;
    generatedAt: string;
    commandCenter: string;
    total: number;
    summary: Summary;
    alerts: Conclusion[];
    priorities: Conclusion[];
}

export default function ExecutiveIndex({ version, generatedAt, commandCenter, total, summary, alerts, priorities }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Executive Command Center" />
            <div className="space-y-6 max-w-4xl">
                <header>
                    <h1 className="text-xl font-semibold">Executive Command Center</h1>
                    <p className="text-xs opacity-70">
                        {commandCenter} · v{version} · generated {generatedAt} · {total} conclusion(s)
                    </p>
                </header>

                <section>
                    <h2 className="text-sm font-semibold mb-2">Summary</h2>
                    <p className="text-sm">
                        {summary.total} total · {summary.immediate} need action now
                    </p>
                    <p className="text-xs opacity-70">
                        By severity: {Object.entries(summary.bySeverity).map(([k, v]) => `${k}:${v}`).join(' · ') || '—'}
                    </p>
                    <p className="text-xs opacity-70">
                        By owner: {Object.entries(summary.byOwner).map(([k, v]) => `${k}:${v}`).join(' · ') || '—'}
                    </p>
                </section>

                <section>
                    <h2 className="text-sm font-semibold mb-2">Alerts ({alerts.length})</h2>
                    {alerts.length === 0 ? (
                        <p className="text-sm opacity-70">No immediate alerts.</p>
                    ) : (
                        <ul className="space-y-2">
                            {alerts.map((c) => <ConclusionCard key={c.id} card={c} />)}
                        </ul>
                    )}
                </section>

                <section>
                    <h2 className="text-sm font-semibold mb-2">Priorities ({priorities.length})</h2>
                    {priorities.length === 0 ? (
                        <p className="text-sm opacity-70">No conclusions.</p>
                    ) : (
                        <ul className="space-y-2">
                            {priorities.map((c) => <ConclusionCard key={c.id} card={c} />)}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
