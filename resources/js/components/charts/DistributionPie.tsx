import { lazy, Suspense } from 'react';
import { useTheme } from '@/hooks/useTheme';
import { getChartPalette, seriesColors } from '@/utils/colors';
import Skeleton from '@/components/ui/Skeleton';
import type { ApexOptions } from 'apexcharts';

const ReactApexChart = lazy(() => import('react-apexcharts'));

interface Props {
    labels: string[];
    values: number[];
    height?: number;
}

export default function DistributionPie({ labels, values, height = 280 }: Props) {
    const { isDark } = useTheme();
    const palette = getChartPalette(isDark);

    const options: ApexOptions = {
        chart: { type: 'donut', background: 'transparent', fontFamily: 'Inter, sans-serif' },
        colors: seriesColors.slice(0, labels.length),
        labels,
        dataLabels: {
            enabled: true,
            formatter: (val) => `${(val as number).toFixed(0)}%`,
            style: { fontSize: '11px', fontWeight: 600 },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '55%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            color: palette.text,
                        },
                    },
                },
            },
        },
        legend: {
            position: 'bottom',
            labels: { colors: palette.text },
            markers: { size: 4 },
        },
        stroke: { width: 0 },
        tooltip: { theme: isDark ? 'dark' : 'light' },
    };

    return (
        <Suspense fallback={<Skeleton variant="chart" />}>
            <ReactApexChart options={options} series={values} type="donut" height={height} />
        </Suspense>
    );
}
