export function formatNumber(number: any, locale: string): string {
    const n = normalizeNumber(number);
    if (n === undefined) {
        return '';
    }

    return new Intl.NumberFormat(locale, {}).format(n);
}

export function normalizeNumber(value: any): number | undefined {
    if (typeof value === 'number') {
        return value;
    }

    if (typeof value === 'string') {
        const parsed = parseFloat(value);
        return isNaN(parsed) ? undefined : parsed;
    }

    return undefined;
}
