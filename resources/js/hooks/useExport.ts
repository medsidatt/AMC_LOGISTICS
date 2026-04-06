import { useCallback } from 'react';
import { exportToCsv } from '@/utils/csv-export';

export function useExport() {
    const download = useCallback(<T extends Record<string, any>>(
        data: T[],
        columns: { key: string; label: string }[],
        filename?: string,
    ) => {
        const name = filename ?? `export-${new Date().toISOString().slice(0, 10)}.csv`;
        exportToCsv(data, columns, name);
    }, []);

    return { download };
}
