import { lazy, Suspense } from 'react';
import { useTheme } from '@/hooks/useTheme';
import { statusColors } from '@/utils/colors';
import Skeleton from '@/components/ui/Skeleton';
import type { ApexOptions } from 'apexcharts';

const ReactApexChart = lazy(() => import('react-apexcharts'));

interface Props {
    label: string;
    value: number; // 0-100
    status: 'green' | 'yellow' | 'red';
}

export default function MaintenanceGauge({ label, value, status }: Props) {
    const { isDark } = useTheme();
    const color = statusColors[status];

    const options: ApexOptions = {
        chart: { type: 'radialBar', background: 'transparent', sparkline: { enabled: true } },
        colors: [color],
        plotOptions: {
            radialBar: {
                startAngle: -135,
                endAngle: 135,
                hollow: { size: '60%' },
                track: {
                    background: isDark ? '#3b4253' : '#f0f0f0',
                    strokeWidth: '100%',
                },
                dataLabels: {
                    name: {
                        show: true,
                        offsetY: -8,
                        color: isDark ? '#b4b7bd' : '#6e6b7b',
                        fontSize: '11px',
                    },
                    value: {
                        offsetY: 4,
                        fontSize: '18px',
                        fontWeight: 700,
                        color: isDark ? '#d0d2d6' : '#4b4b4b',
                        formatter: (val) => `${val}%`,
                    },
                },
            },
        },
        labels: [label],
    };

    return (
        <Suspense fallback={<Skeleton variant="card" />}>
            <ReactApexChart options={options} series={[value]} type="radialBar" height={180} />
        </Suspense>
    );
}
