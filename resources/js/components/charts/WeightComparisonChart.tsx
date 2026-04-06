import { lazy, Suspense } from 'react';
import { useTheme } from '@/hooks/useTheme';
import { getChartPalette, chartColors } from '@/utils/colors';
import Skeleton from '@/components/ui/Skeleton';
import type { ApexOptions } from 'apexcharts';

const ReactApexChart = lazy(() => import('react-apexcharts'));

interface Props {
    labels: string[];
    providerData: number[];
    clientData: number[];
    height?: number;
}

export default function WeightComparisonChart({ labels, providerData, clientData, height = 320 }: Props) {
    const { isDark } = useTheme();
    const palette = getChartPalette(isDark);

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'Inter, sans-serif',
        },
        colors: [chartColors.info, chartColors.warning],
        plotOptions: {
            bar: { horizontal: true, borderRadius: 4, barHeight: '60%' },
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: labels,
            labels: { style: { colors: palette.text, fontSize: '11px' } },
        },
        yaxis: {
            labels: { style: { colors: palette.text, fontSize: '11px' } },
        },
        grid: { borderColor: palette.gridLine, strokeDashArray: 4 },
        legend: {
            position: 'top',
            labels: { colors: palette.text },
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            y: { formatter: (v) => `${v.toLocaleString('fr-FR')} kg` },
        },
    };

    const series = [
        { name: 'Fournisseur', data: providerData },
        { name: 'Client', data: clientData },
    ];

    return (
        <Suspense fallback={<Skeleton variant="chart" />}>
            <ReactApexChart options={options} series={series} type="bar" height={height} />
        </Suspense>
    );
}
