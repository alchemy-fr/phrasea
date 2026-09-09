import {
    format as dfFormat,
    formatDistanceToNow,
    formatISO,
    isValid,
    parseISO,
} from 'date-fns';
import {enUS, fr, de, es, it} from 'date-fns/locale';
import type {Locale as DfLocale} from 'date-fns';

const dfLocales: Record<string, DfLocale> = {en: enUS, fr, de, es, it};

export function getDateFnsLocale(lang: string | undefined): DfLocale {
    const l = (lang ?? 'en').split(/[-_]/)[0];

    return dfLocales[l] ?? enUS;
}

export function toDate(value: unknown): Date | undefined {
    if (value instanceof Date) {
        return value;
    }
    if (typeof value === 'number') {
        // seconds since epoch
        return new Date(value < 1e12 ? value * 1000 : value);
    }
    if (typeof value === 'string' && value) {
        const d = parseISO(value);
        if (isValid(d)) {
            return d;
        }
        const d2 = new Date(value);
        if (isValid(d2)) {
            return d2;
        }
    }

    return undefined;
}

export type DateStyle = 'short' | 'medium' | 'long' | 'relative' | 'iso';

export function formatDateTime(
    value: unknown,
    style: DateStyle = 'medium',
    lang?: string,
    withTime = true
): string {
    const d = toDate(value);
    if (!d) {
        return '';
    }
    const locale = getDateFnsLocale(lang);

    switch (style) {
        case 'relative':
            return formatDistanceToNow(d, {addSuffix: true, locale});
        case 'iso':
            return formatISO(d);
        case 'long':
            return dfFormat(d, withTime ? 'PPPPp' : 'PPPP', {locale});
        case 'short':
            return dfFormat(d, withTime ? 'P p' : 'P', {locale});
        case 'medium':
        default:
            return dfFormat(d, withTime ? 'PP p' : 'PP', {locale});
    }
}

export function formatNumber(value: number, lang?: string): string {
    return new Intl.NumberFormat(lang).format(value);
}

const binaryUnits = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];
const decimalUnits = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB'];

export function formatFileSize(
    size: number,
    binary = true,
    lang?: string
): string {
    const base = binary ? 1024 : 1000;
    const units = binary ? binaryUnits : decimalUnits;
    const unit =
        size > 0
            ? Math.min(
                  Math.floor(Math.log(size) / Math.log(base)),
                  units.length - 1
              )
            : 0;
    const v = Math.round((100 * size) / Math.pow(base, unit)) / 100;

    return `${formatNumber(v, lang)} ${units[unit]}`;
}

export function formatDuration(
    seconds: number,
    style: 'compact' | 'humanized' | 'formatted' = 'compact'
): string {
    const s = Math.max(0, Math.round(seconds));
    const days = Math.floor(s / 86400);
    const hours = Math.floor((s % 86400) / 3600);
    const minutes = Math.floor((s % 3600) / 60);
    const secs = s % 60;
    const pad = (n: number) => n.toString().padStart(2, '0');

    if (style === 'compact') {
        return `${days ? `${days}d ` : ''}${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
    }

    const parts: string[] = [];
    if (days) parts.push(`${days}d`);
    if (hours) parts.push(`${hours}h`);
    if (minutes) parts.push(`${minutes}m`);
    if (secs || parts.length === 0) parts.push(`${secs}s`);

    return parts.join(' ');
}

export function truncate(value: string, max: number): string {
    return value.length > max ? `${value.slice(0, max - 1)}…` : value;
}
