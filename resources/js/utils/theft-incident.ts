/**
 * Formats a raw minute count into a human-readable French duration.
 *  • <60 min     → "42 min"
 *  • <24 h       → "3 h 15 min"
 *  • ≥24 h       → "5 j 9 h"
 */
export function formatMinutes(minutes: number | null | undefined): string {
    if (minutes == null) return '—';
    const m = Math.round(Number(minutes));
    if (!Number.isFinite(m) || m <= 0) return `${m} min`;
    if (m < 60) return `${m} min`;
    const h = Math.floor(m / 60);
    const rest = m % 60;
    if (h < 24) return rest > 0 ? `${h} h ${rest} min` : `${h} h`;
    const d = Math.floor(h / 24);
    const restH = h % 24;
    return restH > 0 ? `${d} j ${restH} h` : `${d} j`;
}

/**
 * Returns a display-friendly title for an incident. For `unauthorized_stop`
 * the raw "Arrêt non autorisé de 7769 min pendant…" is rewritten with a
 * human-readable duration ("5 j 9 h 9 min"). Falls back to parsing the raw
 * minute count from the stored title if `evidence.duration_minutes` is null.
 * Other types are returned as-is.
 */
export function displayIncidentTitle(
    type: string,
    title: string,
    evidence?: Record<string, any> | null,
): string {
    if (type !== 'unauthorized_stop' || !title) {
        return title;
    }
    const fromEvidence = evidence?.duration_minutes != null ? Number(evidence.duration_minutes) : null;
    if (fromEvidence != null && Number.isFinite(fromEvidence)) {
        return title.replace(/de\s+\d+\s*min\b/i, `de ${formatMinutes(fromEvidence)}`);
    }
    // Parse the minute count straight from the title as a last resort.
    const match = title.match(/de\s+(\d+)\s*min\b/i);
    if (match) {
        return title.replace(/de\s+\d+\s*min\b/i, `de ${formatMinutes(Number(match[1]))}`);
    }
    return title;
}

/**
 * Generic helper: find any "<NUMBER> min" inside a free-text string and
 * rewrite it as a human-readable duration ("X j Y h Z min"). Useful for
 * legacy LogisticsAlert messages built before the backend formatter was
 * fixed (e.g. "Arrêt non autorisé de 25988 min pendant la mission #...").
 *
 * Conservative: only touches occurrences ≥ 60 min so "5 min" stays as is.
 */
export function humanizeMinutesInText(text: string | null | undefined): string {
    if (!text) return '';
    return text.replace(/\b(\d+)\s*min\b/gi, (match, raw) => {
        const m = Number(raw);
        if (!Number.isFinite(m) || m < 60) return match;
        return formatMinutes(m);
    });
}
