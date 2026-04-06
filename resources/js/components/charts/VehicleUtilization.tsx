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

export default function VehicleUtilization({ labels, values, height = 320 }: Props) {
    const { isDark } = useTheme();
    const palette = getChartPalette(isDark);

    const options: ApexOptions = {
        chart: { type: 'radialBar', background: 'transparent', fontFamily: 'Inter, sans-serif' },
        colors: seriesColors.slice(0, labels.length),
        plotOptions: {
            radialBar: {
                dataLabels: {
                    name: { fontSize: '12px', color: palette.text },
                    value: {
                        fontSize: '16px',
                        fontWeight: 600,
                        color: palette.text,
                        formatter: (val) => `${val}%`,
                    },
                    total: {
                        show: true,
                        label: 'Moy.',
                        color: palette.text,
                        formatter: (w) => {
                            const avg = w.globals.spikeArrays?.[0]
                                ? Math.round(values.reduce((a, b) => a + b, 0) / values.length)
                                : Math.round(values.reduce((a, b) => a + b, 0) / values.length);
                            return `${avg}%`;
                        },
                    },
                },
                hollow: { size: '45%' },
                track: { background: isDark ? '#3b4253' : '#f0f0f0' },
            },
        },
        labels,
        legend: {
            show: true,
            position: 'bottom',
            labels: { colors: palette.text },
        },
    };

    return (
        <Suspense fallback={<Skeleton variant="chart" />}>
            <ReactApexChart options={options} series={values} type="radialBar" height={height} />
        </Suspense>
    );
}
