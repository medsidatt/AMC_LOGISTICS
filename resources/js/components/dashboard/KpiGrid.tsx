import type { ReactNode } from 'react';

interface KpiGridProps {
    children: ReactNode;
    columns?: 2 | 3 | 4;
}

export default function KpiGrid({ children, columns = 4 }: KpiGridProps) {
    const colsClass = {
        2: 'grid-cols-1 sm:grid-cols-2',
        3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    }[columns];

    return (
        <div className={`grid gap-4 ${colsClass}`}>
            {children}
        </div>
    );
}
