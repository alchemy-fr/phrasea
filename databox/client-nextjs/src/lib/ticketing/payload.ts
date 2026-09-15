/**
 * Validation / sanitization of the ticket payload sent by the browser.
 * Everything here comes from the client, so only known fields are kept and
 * every string is capped before being pushed to JIRA.
 */
import {
    type CreateTicketPayload,
    type TicketClientError,
    type TicketKind,
    type TicketPageContext,
    descriptionMaxLength,
    screenshotMaxBytes,
    summaryMaxLength,
    ticketKinds,
} from '@/features/ticketing/types';

export class TicketValidationError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'TicketValidationError';
    }
}

const maxErrors = 10;
const maxErrorLength = 4_000;
const maxUrlLength = 2_000;
const maxShortLength = 300;

function str(value: unknown, maxLength = maxShortLength): string | undefined {
    if (typeof value !== 'string') {
        return undefined;
    }
    const trimmed = value.trim();

    return trimmed ? trimmed.slice(0, maxLength) : undefined;
}

function num(value: unknown): number | undefined {
    return typeof value === 'number' && Number.isFinite(value)
        ? value
        : undefined;
}

function size(value: any): {width: number; height: number} | undefined {
    const width = num(value?.width);
    const height = num(value?.height);

    return width !== undefined && height !== undefined
        ? {width, height}
        : undefined;
}

function errors(value: unknown): TicketClientError[] | undefined {
    if (!Array.isArray(value)) {
        return undefined;
    }
    const list = value
        .slice(-maxErrors)
        .map(e => ({
            at: str(e?.at) ?? new Date().toISOString(),
            message: str(e?.message, maxShortLength) ?? '',
            source: str(e?.source),
            stack: str(e?.stack, maxErrorLength),
        }))
        .filter(e => e.message);

    return list.length > 0 ? list : undefined;
}

function page(value: any): TicketPageContext {
    const url = str(value?.url, maxUrlLength);
    if (!url) {
        throw new TicketValidationError('Missing page url');
    }

    return {
        url,
        path: str(value?.path, maxUrlLength) ?? '',
        title: str(value?.title),
        referrer: str(value?.referrer, maxUrlLength),
        locale: str(value?.locale, 20),
        dataLocale: str(value?.dataLocale, 20),
        theme: str(value?.theme, 20),
        timezone: str(value?.timezone, 60),
        viewport: size(value?.viewport),
        screen: value?.screen
            ? {
                  ...(size(value.screen) ?? {width: 0, height: 0}),
                  pixelRatio: num(value.screen.pixelRatio) ?? 1,
              }
            : undefined,
        userAgent: str(value?.userAgent, 500),
        capturedAt: str(value?.capturedAt, 40) ?? new Date().toISOString(),
        errors: errors(value?.errors),
    };
}

function screenshot(value: unknown): string | undefined {
    if (value === undefined || value === null || value === '') {
        return undefined;
    }
    if (typeof value !== 'string') {
        throw new TicketValidationError('Invalid screenshot');
    }
    if (!/^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/=\s]+$/.test(value)) {
        throw new TicketValidationError('Unsupported screenshot format');
    }
    const base64 = value.slice(value.indexOf(',') + 1);
    if ((base64.length * 3) / 4 > screenshotMaxBytes) {
        throw new TicketValidationError('Screenshot is too large');
    }

    return value;
}

export function parseTicketPayload(input: unknown): CreateTicketPayload {
    const body = (input ?? {}) as Record<string, unknown>;

    const summary = str(body.summary, summaryMaxLength);
    if (!summary) {
        throw new TicketValidationError('Summary is required');
    }

    const description = str(body.description, descriptionMaxLength);
    if (!description) {
        throw new TicketValidationError('Description is required');
    }

    const kind = ticketKinds.includes(body.kind as TicketKind)
        ? (body.kind as TicketKind)
        : undefined;
    if (!kind) {
        throw new TicketValidationError('Invalid ticket kind');
    }

    return {
        kind,
        summary,
        description,
        page: page(body.page),
        screenshot: screenshot(body.screenshot),
    };
}
