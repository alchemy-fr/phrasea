import ky, {HTTPError, isHTTPError, type KyInstance, type Options} from 'ky';
import {getConfig} from '@/lib/config/ConfigProvider';
import {getAuthClient} from '@/lib/auth/client';

export type QueryParams = Record<string, unknown>;

export type RequestOptions = {
    params?: QueryParams;
    /** Do not attach the bearer token (e.g. S3 presigned URLs, public share) */
    anonymous?: boolean;
    signal?: AbortSignal;
    headers?: Record<string, string | undefined>;
    json?: unknown;
    body?: BodyInit;
    timeout?: number | false;
};

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly data: any,
        public readonly response?: Response
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** API Platform constraint violations */
    get violations(): {propertyPath: string; message: string}[] {
        return this.data?.violations ?? [];
    }
}

export function isApiError(e: unknown, status?: number): e is ApiError {
    return (
        e instanceof ApiError && (status === undefined || e.status === status)
    );
}

export function isAbortError(e: unknown): boolean {
    return (
        (e instanceof DOMException && e.name === 'AbortError') ||
        (e instanceof Error && e.name === 'AbortError')
    );
}

/**
 * Serializes query params the way Symfony expects them:
 * arrays => `key[]=v`, objects => `key[sub]=v`, booleans => `true|false`.
 */
export function buildSearchParams(
    params: QueryParams | undefined,
    target = new URLSearchParams(),
    prefix?: string
): URLSearchParams {
    if (!params) {
        return target;
    }
    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null) {
            continue;
        }
        const name = prefix ? `${prefix}[${key}]` : key;
        if (Array.isArray(value)) {
            value.forEach(v => {
                if (v !== undefined && v !== null) {
                    target.append(`${name}[]`, String(v));
                }
            });
        } else if (typeof value === 'object') {
            buildSearchParams(value as QueryParams, target, name);
        } else {
            target.append(name, String(value));
        }
    }

    return target;
}

let dataLocale: string | undefined;
let uiLocale = 'en';

export function setApiLocales(locales: {
    ui?: string;
    data?: string | undefined;
}): void {
    if (locales.ui) {
        uiLocale = locales.ui;
    }
    if ('data' in locales) {
        dataLocale = locales.data;
    }
}

type Context = {anonymous?: boolean; retried?: boolean};

let instance: KyInstance | undefined;

function createInstance(): KyInstance {
    const config = getConfig();

    return ky.create({
        prefix: config.apiUrl,
        timeout: 60_000,
        retry: {
            limit: 2,
            methods: ['get', 'head', 'options'],
            statusCodes: [408, 429, 502, 503, 504],
        },
        hooks: {
            beforeRequest: [
                async ({request, options}) => {
                    const ctx = options.context as Context;
                    if (!ctx.anonymous) {
                        const token = await getAuthClient()
                            .getAccessToken()
                            .catch(() => undefined);
                        if (token) {
                            request.headers.set(
                                'Authorization',
                                `Bearer ${token}`
                            );
                        }
                    }
                    if (!request.headers.has('Accept')) {
                        request.headers.set(
                            'Accept',
                            'application/ld+json, application/json'
                        );
                    }
                    const languages =
                        typeof navigator !== 'undefined'
                            ? navigator.languages.filter(l => l !== uiLocale)
                            : [];
                    request.headers.set(
                        'Accept-Language',
                        [uiLocale, ...languages].join(', ')
                    );
                    if (dataLocale) {
                        request.headers.set('X-Data-Locale', dataLocale);
                    }
                },
            ],
            afterResponse: [
                async ({request, options, response}) => {
                    const ctx = options.context as Context;
                    if (
                        response.status === 401 &&
                        !ctx.anonymous &&
                        !ctx.retried
                    ) {
                        const auth = getAuthClient();
                        if (auth.hasSession()) {
                            const tokens = await auth
                                .refresh()
                                .catch(() => undefined);
                            if (tokens) {
                                const retried = new Request(request, {
                                    headers: new Headers(request.headers),
                                });
                                retried.headers.set(
                                    'Authorization',
                                    `Bearer ${tokens.accessToken}`
                                );

                                return ky(retried, {
                                    ...(options as Options),
                                    prefix: '',
                                    context: {...ctx, retried: true},
                                });
                            }
                        }
                    }

                    return response;
                },
            ],
            beforeError: [
                ({error}) => {
                    if (!isHTTPError(error)) {
                        return error;
                    }
                    const {response} = error;
                    const data: any =
                        typeof error.data === 'object' ? error.data : undefined;
                    const message =
                        data?.['hydra:description'] ??
                        data?.detail ??
                        data?.message ??
                        data?.error_description ??
                        (typeof error.data === 'string' && error.data
                            ? error.data
                            : undefined) ??
                        `${response.status} ${response.statusText}`;

                    return new ApiError(
                        message,
                        response.status,
                        data ?? error.data,
                        response
                    );
                },
            ],
        },
    });
}

export function getHttp(): KyInstance {
    return (instance ??= createInstance());
}

function isAbsolute(path: string): boolean {
    return /^https?:\/\//.test(path);
}

function toKyOptions(
    {params, anonymous, signal, headers, json, body, timeout}: RequestOptions,
    method: string,
    url: string
): Options {
    const h: Record<string, string> = {};
    Object.entries(headers ?? {}).forEach(([k, v]) => {
        if (v !== undefined) {
            h[k] = v;
        }
    });

    return {
        method,
        searchParams: params ? buildSearchParams(params) : undefined,
        signal,
        headers: h,
        json,
        body,
        timeout,
        context: {anonymous} satisfies Context,
        ...(isAbsolute(url) ? {prefix: ''} : {}),
    };
}

async function request<T>(
    method: string,
    path: string,
    options: RequestOptions = {}
): Promise<T> {
    const response = await getHttp()(path, toKyOptions(options, method, path));
    if (response.status === 204) {
        return undefined as T;
    }
    const contentType = response.headers.get('content-type') ?? '';
    if (contentType.includes('json')) {
        return (await response.json()) as T;
    }

    return (await response.text()) as unknown as T;
}

export const api = {
    get: <T>(path: string, options?: RequestOptions) =>
        request<T>('get', path, options),
    post: <T>(path: string, json?: unknown, options?: RequestOptions) =>
        request<T>('post', path, {...options, json: json ?? {}}),
    put: <T>(path: string, json?: unknown, options?: RequestOptions) =>
        request<T>('put', path, {...options, json: json ?? {}}),
    patch: <T>(path: string, json?: unknown, options?: RequestOptions) =>
        request<T>('patch', path, {
            ...options,
            json,
            headers: {
                'Content-Type': 'application/merge-patch+json',
                ...options?.headers,
            },
        }),
    delete: <T = void>(path: string, options?: RequestOptions) =>
        request<T>('delete', path, options),
    /** Raw fetch giving access to the Response (blobs, headers) */
    raw: (path: string, options: RequestOptions & {method?: string} = {}) =>
        getHttp()(path, toKyOptions(options, options.method ?? 'get', path)),
};

export {HTTPError};
