/**
 * Export data to CSV (Excel-compatible with BOM + semicolon separator for French locale)
 */
export function exportToCsv<T extends Record<string, any>>(
    data: T[],
    columns: { key: string; label: string }[],
    filename: string = 'export.csv'
) {
    const BOM = '\uFEFF';
    const sep = ';';

    const header = columns.map((c) => `"${c.label}"`).join(sep);

    const rows = data.map((row) =>
        columns.map((col) => {
            let value = row[col.key];
            if (value && typeof value === 'object') {
                value = value.name ?? value.matricule ?? value.label ?? JSON.stringify(value);
            }
            const str = String(value ?? '').replace(/"/g, '""');
            return `"${str}"`;
        }).join(sep)
    );

    const csv = BOM + [header, ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}
