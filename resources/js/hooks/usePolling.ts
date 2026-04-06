import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';

interface UsePollingOptions {
    interval: number; // seconds
    only?: string[];
    enabled?: boolean;
}

export function usePolling({ interval, only, enabled = true }: UsePollingOptions) {
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (!enabled || interval <= 0) return;

        timer.current = setInterval(() => {
            router.reload({
                only: only,
                preserveState: true,
                preserveScroll: true,
            });
        }, interval * 1000);

        return () => {
            if (timer.current) clearInterval(timer.current);
        };
    }, [interval, enabled, only]);
}
