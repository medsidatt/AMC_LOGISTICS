export function exportToCsv<T extends Record<string, any>>(
    data: T[],
    columns: { key: string; label: string }[],
    filename = 'export.csv',
) {
    const header = columns.map((c) => c.label).join(';');
    const rows = data.map((row) =>
        columns.map((c) => {
            const val = row[c.key];
            if (val == null) return '';
            const str = String(val).replace(/"/g, '""');
            return `"${str}"`;
        }).join(';'),
    );

    const bom = '\uFEFF';
    const csv = bom + [header, ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
