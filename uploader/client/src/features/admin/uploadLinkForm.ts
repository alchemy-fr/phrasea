import type {UploadLink, UploadLinkInput} from '@/types/api';

export type SizeUnit = 'MB' | 'GB';

export const SIZE_UNITS: Record<SizeUnit, number> = {
    MB: 1024 ** 2,
    GB: 1024 ** 3,
};

/** Days a new link stays valid by default */
export const DEFAULT_VALIDITY_DAYS = 7;

/** What the dialog edits: the inputs as typed */
export type UploadLinkFormValues = {
    target: string;
    name: string;
    /** `datetime-local` value, empty for no expiration */
    expiresAt: string;
    maxAssets: string;
    maxTotalSize: string;
    sizeUnit: SizeUnit;
};

const pad = (n: number) => String(n).padStart(2, '0');

/** A date as an `<input type="datetime-local">` holds it, in local time */
export function toDateTimeLocal(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate()
    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The ISO date of a `datetime-local` value, null when empty or invalid */
export function fromDateTimeLocal(value: string): string | null {
    if (!value) {
        return null;
    }
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

/** A size in bytes, in the largest unit that keeps it whole (MB at least) */
export function splitSize(bytes: number | null | undefined): {
    value: string;
    unit: SizeUnit;
} {
    if (!bytes) {
        return {value: '', unit: 'GB'};
    }
    if (bytes % SIZE_UNITS.GB === 0) {
        return {value: String(bytes / SIZE_UNITS.GB), unit: 'GB'};
    }

    return {
        value: String(Math.round((bytes / SIZE_UNITS.MB) * 100) / 100),
        unit: 'MB',
    };
}

/** Bytes of a typed size, null when empty */
export function toBytes(value: string, unit: SizeUnit): number | null {
    const n = parseFloat(value.replace(',', '.'));

    return value.trim() === '' || Number.isNaN(n)
        ? null
        : Math.round(n * SIZE_UNITS[unit]);
}

function toCount(value: string): number | null {
    const n = parseInt(value, 10);

    return value.trim() === '' || Number.isNaN(n) ? null : n;
}

/** The values of the dialog: a new link expires in a week by default */
export function initialFormValues(
    link?: UploadLink,
    now: Date = new Date()
): UploadLinkFormValues {
    if (!link) {
        const expiresAt = new Date(now);
        expiresAt.setDate(expiresAt.getDate() + DEFAULT_VALIDITY_DAYS);

        return {
            target: '',
            name: '',
            expiresAt: toDateTimeLocal(expiresAt),
            maxAssets: '',
            maxTotalSize: '',
            sizeUnit: 'GB',
        };
    }
    const size = splitSize(link.maxTotalSize);

    return {
        target: link.target.id,
        name: link.name ?? '',
        expiresAt: link.expiresAt
            ? toDateTimeLocal(new Date(link.expiresAt))
            : '',
        maxAssets:
            typeof link.maxAssets === 'number' ? String(link.maxAssets) : '',
        maxTotalSize: size.value,
        sizeUnit: size.unit,
    };
}

/** What is sent to the API; the target only on creation */
export function toInput(
    values: UploadLinkFormValues,
    creating: boolean
): UploadLinkInput {
    return {
        ...(creating ? {target: `/targets/${values.target}`} : {}),
        name: values.name.trim() || null,
        expiresAt: fromDateTimeLocal(values.expiresAt),
        maxAssets: toCount(values.maxAssets),
        maxTotalSize: toBytes(values.maxTotalSize, values.sizeUnit),
    };
}
