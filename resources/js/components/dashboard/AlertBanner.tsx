import { AlertTriangle, X } from 'lucide-react';
import { useState } from 'react';

interface AlertBannerProps {
    count: number;
    message?: string;
    href?: string;
}

export default function AlertBanner({ count, message, href }: AlertBannerProps) {
    const [dismissed, setDismissed] = useState(false);

    if (count === 0 || dismissed) return null;

    return (
        <div className="flex items-center justify-between gap-3 px-4 py-3 mb-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400">
            <div className="flex items-center gap-3">
                <AlertTriangle size={18} />
                <p className="text-sm font-medium">
                    {message ?? `${count} alerte${count > 1 ? 's' : ''} non résolue${count > 1 ? 's' : ''}`}
                </p>
            </div>
            <div className="flex items-center gap-2">
                {href && (
                    <a href={href} className="text-xs font-medium underline hover:no-underline">
                        Voir
                    </a>
                )}
                <button onClick={() => setDismissed(true)} className="p-1 rounded hover:bg-amber-500/10">
                    <X size={14} />
                </button>
            </div>
        </div>
    );
}
