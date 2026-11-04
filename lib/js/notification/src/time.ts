const DIVISIONS: {amount: number; unit: Intl.RelativeTimeFormatUnit}[] = [
    {amount: 60, unit: 'seconds'},
    {amount: 60, unit: 'minutes'},
    {amount: 24, unit: 'hours'},
    {amount: 7, unit: 'days'},
    {amount: 4.34524, unit: 'weeks'},
    {amount: 12, unit: 'months'},
    {amount: Number.POSITIVE_INFINITY, unit: 'years'},
];

/**
 * Formats an ISO date as a localized relative time (e.g. "3 hours ago").
 */
export function formatRelativeTime(iso: string, locale?: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const rtf = new Intl.RelativeTimeFormat(locale, {numeric: 'auto'});
    let duration = (date.getTime() - Date.now()) / 1000;

    for (const division of DIVISIONS) {
        if (Math.abs(duration) < division.amount) {
            return rtf.format(Math.round(duration), division.unit);
        }
        duration /= division.amount;
    }

    return rtf.format(Math.round(duration), 'years');
}
