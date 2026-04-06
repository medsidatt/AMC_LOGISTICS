import { lazy, Suspense } from 'react';
import { useTheme } from '@/hooks/useTheme';
import { getChartPalette, chartColors } from '@/utils/colors';
import Skeleton from '@/components/ui/Skeleton';
import type { ApexOptions } from 'apexcharts';

const ReactApexChart = lazy(() => import('react-apexcharts'));

interface TonnageChartProps {
    months: string[];
    providerData: number[];
    clientData?: number[];
    gapData?: number[];
    height?: number;
}

export default function TonnageChart({ months, providerData, clientData, gapData, height = 320 }: TonnageChartProps) {
    const { isDark } = useTheme();
    const palette = getChartPalette(isDark);

    const series: ApexAxisChartSeries = [
        { name: 'Fournisseur', data: providerData },
    ];
    if (clientData) series.push({ name: 'Client', data: clientData });
    if (gapData) series.push({ name: 'Écart', data: gapData, type: 'line' });

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'Inter, sans-serif',
        },
        colors: [chartColors.primary, chartColors.success, chartColors.danger],
        plotOptions: {
            bar: { borderRadius: 6, columnWidth: '55%' },
        },
        dataLabels: { enabled: false },
        stroke: { width: gapData ? [0, 0, 3] : 0, curve: 'smooth' },
        xaxis: {
            categories: months,
            labels: { style: { colors: palette.text, fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { colors: palette.text, fontSize: '11px' },
                formatter: (v) => `${(v / 1000).toFixed(0)}K`,
            },
        },
        grid: {
            borderColor: palette.gridLine,
            strokeDashArray: 4,
            padding: { left: 0, right: 0 },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            labels: { colors: palette.text },
            markers: { size: 4, shape: 'circle' as const },
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            y: { formatter: (v) => `${v.toLocaleString('fr-FR')} kg` },
        },
    };

    return (
        <Suspense fallback={<Skeleton variant="chart" />}>
            <ReactApexChart options={options} series={series} type="bar" height={height} />
        </Suspense>
    );
}
