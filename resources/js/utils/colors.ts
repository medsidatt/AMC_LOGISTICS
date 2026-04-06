export const chartColors = {
    primary: '#7367f0',
    success: '#28c76f',
    danger: '#ea5455',
    warning: '#ff9f43',
    info: '#00cfe8',
    purple: '#a855f7',
    slate: '#64748b',
};

export const chartColorsDark = {
    ...chartColors,
    gridLine: '#3b4253',
    text: '#b4b7bd',
    tooltip: '#283046',
};

export const chartColorsLight = {
    ...chartColors,
    gridLine: '#ebe9f1',
    text: '#6e6b7b',
    tooltip: '#ffffff',
};

export const statusColors = {
    red: '#ea5455',
    yellow: '#ff9f43',
    green: '#28c76f',
};

export function getChartPalette(isDark: boolean) {
    return isDark ? chartColorsDark : chartColorsLight;
}

export const seriesColors = [
    chartColors.primary,
    chartColors.success,
    chartColors.danger,
    chartColors.warning,
    chartColors.info,
    chartColors.purple,
];
