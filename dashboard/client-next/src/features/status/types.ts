import type {AppId} from '@/lib/config/types';

/**
 * - `up`: the API answers and its health checks pass
 * - `degraded`: the API answers but one of its checks fails (database,
 *   message broker…)
 * - `down`: the API does not answer
 */
export type ServiceStatus = 'up' | 'degraded' | 'down';

export type ServiceStatuses = Partial<Record<AppId, ServiceStatus>>;
