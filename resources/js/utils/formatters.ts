export function formatNumber(value: number, decimals = 0): string {
    const safe = Number(value) || 0;
    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(safe);
}

export function formatWeight(kg: number): string {
    if (Math.abs(kg) >= 1000) {
        return `${formatNumber(kg / 1000, 1)} T`;
    }
    return `${formatNumber(kg, 0)} kg`;
}

export function formatPercent(value: number, decimals = 1): string {
    return `${value >= 0 ? '+' : ''}${formatNumber(value, decimals)}%`;
}

export function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function formatDateTime(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatCompact(value: number): string {
    if (Math.abs(value) >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(1)}M`;
    }
    if (Math.abs(value) >= 1_000) {
        return `${(value / 1_000).toFixed(1)}K`;
    }
    return String(Math.round(value));
}

export function calcChange(current: number, previous: number): number {
    if (previous === 0) return current > 0 ? 100 : 0;
    return ((current - previous) / Math.abs(previous)) * 100;
}
