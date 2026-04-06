import { lazy, Suspense } from 'react';
import { useTheme } from '@/hooks/useTheme';
import { getChartPalette, chartColors } from '@/utils/colors';
import Skeleton from '@/components/ui/Skeleton';
import type { ApexOptions } from 'apexcharts';

const ReactApexChart = lazy(() => import('react-apexcharts'));

interface TimelineEvent {
    truck: string;
    driver: string;
    start: string;
    end: string;
    reference: string;
    hasConflict?: boolean;
}

interface Props {
    events: TimelineEvent[];
    height?: number;
}

export default function RotationTimeline({ events, height = 350 }: Props) {
    const { isDark } = useTheme();
    const palette = getChartPalette(isDark);

    const trucks = [...new Set(events.map((e) => e.truck))];

    const series = trucks.map((truck) => ({
        name: truck,
        data: events
            .filter((e) => e.truck === truck)
            .map((e) => ({
                x: e.driver,
                y: [new Date(e.start).getTime(), new Date(e.end || e.start).getTime()],
                fillColor: e.hasConflict ? chartColors.danger : chartColors.primary,
            })),
    }));

    const options: ApexOptions = {
        chart: {
            type: 'rangeBar',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'Inter, sans-serif',
        },
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '60%',
                borderRadius: 4,
            },
        },
        xaxis: {
            type: 'datetime',
            labels: { style: { colors: palette.text, fontSize: '10px' } },
        },
        yaxis: {
            labels: { style: { colors: palette.text, fontSize: '11px' } },
        },
        grid: { borderColor: palette.gridLine, strokeDashArray: 4 },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            custom: ({ seriesIndex, dataPointIndex, w }) => {
                const data = w.config.series[seriesIndex].data[dataPointIndex];
                const event = events.find(
                    (e) => e.truck === trucks[seriesIndex] && e.driver === data.x,
                );
                return `<div class="p-2 text-xs">
                    <strong>${event?.reference}</strong><br/>
                    ${event?.truck} — ${event?.driver}<br/>
                    ${event?.hasConflict ? '<span style="color:#ea5455">⚠ Conflit</span>' : ''}
                </div>`;
            },
        },
        legend: { show: false },
    };

    if (events.length === 0) {
        return (
            <div className="flex items-center justify-center h-64 text-[var(--color-text-muted)] text-sm">
                Aucune rotation pour cette période
            </div>
        );
    }

    return (
        <Suspense fallback={<Skeleton variant="chart" />}>
            <ReactApexChart options={options} series={series} type="rangeBar" height={height} />
        </Suspense>
    );
}
