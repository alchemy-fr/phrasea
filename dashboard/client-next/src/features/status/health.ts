import 'server-only';
import {getApps} from '@/lib/config/server';
import {httpGet, isFalse, type HttpGet} from '@/lib/server/http';
import type {AppId} from '@/lib/config/types';
import type {ServiceStatus, ServiceStatuses} from './types';

/** The health check route of the APIs (`HealthCheckAction` of core-bundle) */
export const HEALTH_CHECK_PATH = '/_health/check';

export const PROBE_TIMEOUT_MS = 5000;
export const DEFAULT_STATUS_TTL = 15;

/**
 * Reads the answer of a health check. The API answers 503 with the list of
 * its checks when one fails; a 503 without it comes from the proxy, the API
 * being stopped. An API refusing an anonymous health check still answered.
 */
export function classifyHealthResponse(
    status: number,
    body: string
): ServiceStatus {
    if (status === 200 || status === 401 || status === 403) {
        return 'up';
    }
    if (status === 503) {
        try {
            const data: unknown = JSON.parse(body);
            if (data && typeof data === 'object') {
                return 'degraded';
            }
        } catch {
            // not the API
        }
    }

    return 'down';
}

export async function probeApi(
    apiUrl: string,
    {get = httpGet, verifySsl = true}: {get?: HttpGet; verifySsl?: boolean} = {}
): Promise<ServiceStatus> {
    try {
        const {status, body} = await get(`${apiUrl}${HEALTH_CHECK_PATH}`, {
            headers: {Accept: 'application/json'},
            rejectUnauthorized: verifySsl,
            timeout: PROBE_TIMEOUT_MS,
        });

        return classifyHealthResponse(status, body);
    } catch {
        return 'down';
    }
}

export type StatusLoader = () => Promise<ServiceStatuses>;

/**
 * Probes every API of the stack, at most once every `STATUS_TTL` seconds
 * whatever the number of visitors: the answer is shared.
 */
export function createStatusLoader({
    env = process.env,
    get = httpGet,
    now = () => Date.now(),
}: {
    env?: Record<string, string | undefined>;
    get?: HttpGet;
    now?: () => number;
} = {}): StatusLoader {
    let cached: ServiceStatuses | undefined;
    let expiresAt = 0;
    let pending: Promise<ServiceStatuses> | undefined;
    const ttl = Number.parseInt(env.STATUS_TTL ?? '', 10);

    const load = async (): Promise<ServiceStatuses> => {
        const verifySsl = !isFalse(env.VERIFY_SSL);
        const entries = await Promise.all(
            Object.entries(getApps(env)).map(
                async ([id, app]) =>
                    [
                        id as AppId,
                        await probeApi(app.apiUrl, {get, verifySsl}),
                    ] as const
            )
        );

        return Object.fromEntries(entries);
    };

    return async () => {
        if (cached && now() < expiresAt) {
            return cached;
        }
        pending ??= load()
            .then(statuses => {
                cached = statuses;
                expiresAt =
                    now() +
                    (Number.isNaN(ttl) ? DEFAULT_STATUS_TTL : ttl) * 1000;

                return statuses;
            })
            .finally(() => {
                pending = undefined;
            });

        return pending;
    };
}

let loader: StatusLoader | undefined;

export function getServiceStatuses(): Promise<ServiceStatuses> {
    return (loader ??= createStatusLoader())();
}
