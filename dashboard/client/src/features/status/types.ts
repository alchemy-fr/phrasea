import type {AppId} from '@/lib/config/types';

/**
 * - `up`: the API answers and its health checks pass
 * - `degraded`: the API answers but one of its checks fails (database,
 *   message broker…)
 * - `down`: the API does not answer
 */
export type ServiceStatus = 'up' | 'degraded' | 'down';

/**
 * The state of an API: its status, and the version it runs (`/_health/version`
 * of core-bundle, e.g. `5.0.0` or `next@34886e8`) when it could be read.
 */
export type ServiceState = {
    status: ServiceStatus;
    version?: string;
};

export type ServiceStatuses = Partial<Record<AppId, ServiceState>>;
