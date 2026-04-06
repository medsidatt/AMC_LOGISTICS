import type { Insight } from '@/types/models';
import { calcChange } from './formatters';

export function generateAdminInsights(data: {
    trucksCount: number;
    driversCount: number;
    tripsToday: number;
    tripsTodayYesterday?: number;
    tonnageMonth: number;
    tonnageLastMonth?: number;
    unresolvedAlerts: number;
    trucksDueMaintenance: number;
}): Insight[] {
    const insights: Insight[] = [];

    if (data.tripsTodayYesterday !== undefined && data.tripsToday > 0) {
        const change = calcChange(data.tripsToday, data.tripsTodayYesterday);
        if (Math.abs(change) > 10) {
            insights.push({
                type: change > 0 ? 'success' : 'warning',
                icon: 'trending',
                message: `Rotations ${change > 0 ? 'en hausse' : 'en baisse'} de ${Math.abs(change).toFixed(0)}% vs hier`,
            });
        }
    }

    if (data.tonnageLastMonth !== undefined && data.tonnageMonth > 0) {
        const change = calcChange(data.tonnageMonth, data.tonnageLastMonth);
        if (Math.abs(change) > 5) {
            insights.push({
                type: change > 0 ? 'success' : 'warning',
                icon: 'weight',
                message: `Tonnage mensuel ${change > 0 ? 'en hausse' : 'en baisse'}`,
                metric: `${change > 0 ? '+' : ''}${change.toFixed(1)}%`,
            });
        }
    }

    if (data.trucksDueMaintenance > 0) {
        insights.push({
            type: 'danger',
            icon: 'wrench',
            message: `${data.trucksDueMaintenance} véhicule${data.trucksDueMaintenance > 1 ? 's' : ''} nécessite${data.trucksDueMaintenance > 1 ? 'nt' : ''} une maintenance`,
        });
    }

    if (data.unresolvedAlerts > 0) {
        insights.push({
            type: 'warning',
            icon: 'alert',
            message: `${data.unresolvedAlerts} alerte${data.unresolvedAlerts > 1 ? 's' : ''} non résolue${data.unresolvedAlerts > 1 ? 's' : ''}`,
        });
    }

    const utilization = data.trucksCount > 0
        ? Math.round((data.driversCount / data.trucksCount) * 100)
        : 0;
    if (utilization < 80 && data.trucksCount > 0) {
        insights.push({
            type: 'info',
            icon: 'truck',
            message: `Taux d'utilisation de la flotte`,
            metric: `${utilization}%`,
        });
    }

    return insights;
}

export function generateTransportInsights(data: {
    totalGap: number;
    totalTransported: number;
    anomaliesCount: number;
    totalTrips: number;
    suspiciousDrivers: number;
}): Insight[] {
    const insights: Insight[] = [];

    if (data.totalTransported > 0) {
        const gapPercent = Math.abs(data.totalGap / data.totalTransported) * 100;
        if (gapPercent > 2) {
            insights.push({
                type: 'danger',
                icon: 'scale',
                message: `Écart de poids significatif`,
                metric: `${gapPercent.toFixed(1)}%`,
            });
        }
    }

    if (data.totalTrips > 0) {
        const anomalyRate = (data.anomaliesCount / data.totalTrips) * 100;
        if (anomalyRate > 10) {
            insights.push({
                type: 'warning',
                icon: 'alert',
                message: `Taux d'anomalies élevé`,
                metric: `${anomalyRate.toFixed(0)}%`,
            });
        }
    }

    if (data.suspiciousDrivers > 0) {
        insights.push({
            type: 'danger',
            icon: 'user',
            message: `${data.suspiciousDrivers} conducteur${data.suspiciousDrivers > 1 ? 's' : ''} avec écarts > seuil`,
        });
    }

    return insights;
}
