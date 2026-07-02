import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { ConclusionCard, type Conclusion } from '@/components/command-center/ConclusionCard';

/**
 * R2.2 — Operations Command Center, minimal end-to-end proof.
 *
 * This page only DISPLAYS the presentation-ready response produced by the Operations
 * Command Center. It contains no business logic, no calculation, no charts — it exists to
 * prove the architecture flows through to React. UI/design is deliberately out of scope.
 */

interface Queue {
    key: string;
    label: string;
    count: number;
    cards: Conclusion[];
}

interface Props {
    version: number;
    generatedAt: string;
    commandCenter: string;
    total: number;
    queues: Queue[];
    problems: Conclusion[];
    actions: Conclusion[];
}

export default function OperationsCommandCenter({ version, generatedAt, commandCenter, total, queues, problems, actions }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Operations Command Center" />
            <div className="space-y-6 max-w-4xl">
                <header>
                    <h1 className="text-xl font-semibold">Operations Command Center</h1>
                    <p className="text-xs opacity-70">
                        {commandCenter} · v{version} · generated {generatedAt} · {total} conclusion(s)
                    </p>
                </header>

                <section>
                    <h2 className="text-sm font-semibold mb-2">Queues ({queues.length})</h2>
                    {queues.length === 0 ? (
                        <p className="text-sm opacity-70">No queues.</p>
                    ) : (
                        <div className="space-y-4">
                            {queues.map((q) => (
                                <div key={q.key}>
                                    <h3 className="text-sm font-medium">{q.label} ({q.count})</h3>
                                    <ul className="space-y-2 mt-1">
                                        {q.cards.map((c) => <ConclusionCard key={c.id} card={c} />)}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section>
                    <h2 className="text-sm font-semibold mb-2">Problems ({problems.length})</h2>
                    {problems.length === 0 ? (
                        <p className="text-sm opacity-70">No immediate problems.</p>
                    ) : (
                        <ul className="space-y-2">
                            {problems.map((c) => <ConclusionCard key={c.id} card={c} />)}
                        </ul>
                    )}
                </section>

                <section>
                    <h2 className="text-sm font-semibold mb-2">Actions ({actions.length})</h2>
                    {actions.length === 0 ? (
                        <p className="text-sm opacity-70">No actions.</p>
                    ) : (
                        <ul className="space-y-2">
                            {actions.map((c) => <ConclusionCard key={c.id} card={c} />)}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
