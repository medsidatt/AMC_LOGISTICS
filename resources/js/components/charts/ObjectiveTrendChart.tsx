import { lazy, Suspense } from 'react';
import { useTheme } from '@/hooks/useTheme';
import { getChartPalette, chartColors } from '@/utils/colors';
import Skeleton from '@/components/ui/Skeleton';
import type { ApexOptions } from 'apexcharts';

const ReactApexChart = lazy(() => import('react-apexcharts'));

interface Props {
    labels: string[];
    target: number[];
    achieved: number[];
    height?: number;
}

/** Objectif (ligne) vs réalisé (barres) en tonnage, par période. */
export default function ObjectiveTrendChart({ labels, target, achieved, height = 300 }: Props) {
    const { isDark } = useTheme();
    const palette = getChartPalette(isDark);

    const series: ApexAxisChartSeries = [
        { name: 'Réalisé', type: 'column', data: achieved },
        { name: 'Objectif', type: 'line', data: target },
    ];

    const options: ApexOptions = {
        chart: { type: 'line', toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
        colors: [chartColors.success, chartColors.primary],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        stroke: { width: [0, 3], curve: 'smooth' },
        xaxis: {
            categories: labels,
            labels: { style: { colors: palette.text, fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { colors: palette.text, fontSize: '11px' }, formatter: (v) => `${Math.round(v).toLocaleString('fr-FR')}` } },
        grid: { borderColor: palette.gridLine, strokeDashArray: 4 },
        legend: { position: 'top', horizontalAlign: 'left', labels: { colors: palette.text }, markers: { size: 4, shape: 'circle' as const } },
        tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: (v) => `${v.toLocaleString('fr-FR')} t` } },
    };

    return (
        <Suspense fallback={<Skeleton variant="chart" />}>
            <ReactApexChart options={options} series={series} type="line" height={height} />
        </Suspense>
    );
}
