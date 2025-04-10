type AnyDate = Date | string | number | null;

export function dateToTimestamp(date: AnyDate): number | null | undefined {
    if (date === null) {
        return null;
    }

    const d = getDate(date);
    if (d) {
        return Math.round(d.getTime() / 1000);
    }
}

export function dateToStringDate(date: AnyDate): string | null | undefined {
    if (date === null) {
        return null;
    }

    const d = getDate(date);
    if (d) {
        return d.toISOString().replace(/\.\d+Z$/, '');
    }
}

export function getDate(date: AnyDate): Date | undefined {
    if (typeof date === 'string') {
        if (date.match(/^\d+$/)) {
            return getDate(parseInt(date));
        }

        if (!date.endsWith('Z')) {
            date += 'Z';
        }

        return new Date(date);
    } else if (typeof date === 'number') {
        if (isNaN(date)) {
            return undefined;
        }

        return new Date(date * 1000);
    } else if (date instanceof Date) {
        return date;
    }
}
