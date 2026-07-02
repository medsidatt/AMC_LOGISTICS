import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import { formatNumber } from '@/utils/formatters';
import { Fuel, ArrowUpRight, ArrowDownRight } from 'lucide-react';

export interface FuelComparisonRow {
    month: string;
    month_label: string;
    edk_litres: number;
    fleeti_litres: number;
    gap_litres: number;
    gap_pct: number | null;
    km: number;
    l_per_100km: number | null;
}

interface Props {
    rows: FuelComparisonRow[];
}

function gapBadge(gap: number, pct: number | null) {
    if (Math.abs(gap) < 1) {
        return <Badge variant="success">OK</Badge>;
    }
    if (gap > 0) {
        return (
            <Badge variant="warning">
                <ArrowUpRight size={12} className="inline mr-0.5" />
                +{formatNumber(gap, 1)} L {pct !== null && `(+${formatNumber(pct, 1)}%)`}
            </Badge>
        );
    }
    return (
        <Badge variant="info">
            <ArrowDownRight size={12} className="inline mr-0.5" />
            {formatNumber(gap, 1)} L {pct !== null && `(${formatNumber(pct, 1)}%)`}
        </Badge>
    );
}

export default function FuelComparisonSection({ rows }: Props) {
    if (rows.length === 0) {
        return null;
    }

    const totalEdk = rows.reduce((s, r) => s + r.edk_litres, 0);
    const totalFleeti = rows.reduce((s, r) => s + r.fleeti_litres, 0);
    const totalGap = totalEdk - totalFleeti;
    const totalGapPct = totalFleeti > 0 ? (totalGap / totalFleeti) * 100 : null;

    return (
        <Card
            className="mb-6"
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Fuel size={16} className="text-[var(--color-primary)]" />
                        <span className="text-sm font-semibold">Comparaison carburant mensuelle</span>
                    </div>
                    <span className="text-xs text-[var(--color-text-muted)]">EDK rechargé vs Fleeti consommé</span>
                </div>
            }
        >
            <div className="overflow-x-auto">
                <table className="w-full text-sm min-w-[600px]">
                    <thead>
                        <tr className="text-xs text-[var(--color-text-muted)] uppercase border-b border-[var(--color-border)]">
                            <th className="text-left py-2 pr-3">Mois</th>
                            <th className="text-right py-2 pr-3">EDK est. (rechargé)</th>
                            <th className="text-right py-2 pr-3">Fleeti consommé</th>
                            <th className="text-right py-2 pr-3">Écart</th>
                            <th className="text-right py-2 pr-3">Km</th>
                            <th className="text-right py-2">L/100km</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.month} className="border-b border-[var(--color-border)] last:border-0">
                                <td className="py-2 pr-3 capitalize">{r.month_label}</td>
                                <td className="py-2 pr-3 text-right font-medium">
                                    {r.edk_litres > 0 ? `${formatNumber(r.edk_litres, 1)} L` : '-'}
                                </td>
                                <td className="py-2 pr-3 text-right font-medium">
                                    {r.fleeti_litres > 0 ? `${formatNumber(r.fleeti_litres, 1)} L` : '-'}
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {r.edk_litres > 0 && r.fleeti_litres > 0
                                        ? gapBadge(r.gap_litres, r.gap_pct)
                                        : <span className="text-[var(--color-text-muted)] text-xs">données partielles</span>}
                                </td>
                                <td className="py-2 pr-3 text-right">{r.km > 0 ? formatNumber(r.km, 0) : '-'}</td>
                                <td className="py-2 text-right font-medium">
                                    {r.l_per_100km !== null ? formatNumber(r.l_per_100km, 1) : '-'}
                                </td>
                            </tr>
                        ))}
                        <tr className="border-t-2 border-[var(--color-border)] font-bold">
                            <td className="py-2 pr-3">Total ({rows.length} mois)</td>
                            <td className="py-2 pr-3 text-right">{formatNumber(totalEdk, 1)} L</td>
                            <td className="py-2 pr-3 text-right">{formatNumber(totalFleeti, 1)} L</td>
                            <td className="py-2 pr-3 text-right">
                                {totalEdk > 0 && totalFleeti > 0
                                    ? gapBadge(totalGap, totalGapPct)
                                    : <span className="text-[var(--color-text-muted)] text-xs">-</span>}
                            </td>
                            <td className="py-2 pr-3"></td>
                            <td className="py-2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </Card>
    );
}
