import {api, type QueryParams} from './http';
import {toPage} from './hydra';
import {
    type AuditLog,
    type AuditLogActorType,
    EntityName,
    type HydraCollection,
    type Page,
} from '@/types/api';

export type AuditLogFilters = {
    /** Action keys, e.g. `asset_moved` */
    actions?: string[];
    actorType?: AuditLogActorType;
    /** Object type key, e.g. `asset` */
    objectType?: string;
    objectId?: string;
    /** The acting user, or the admin impersonating them */
    userId?: string;
    workspaceId?: string;
    correlationId?: string;
    /** ISO dates */
    dateAfter?: string;
    dateBefore?: string;
};

/**
 * Entries of the audit log (admins), the last one first. `next` is the `next`
 * link of a previous page: it replaces the filters, which it carries.
 */
export async function getAuditLogs(
    filters: AuditLogFilters = {},
    next?: string
): Promise<Page<AuditLog>> {
    if (next) {
        return toPage(await api.get<HydraCollection<AuditLog>>(next));
    }

    return toPage(
        await api.get<HydraCollection<AuditLog>>(`/${EntityName.AuditLog}`, {
            params: auditLogParams(filters),
        })
    );
}

export function getAuditLog(id: string): Promise<AuditLog> {
    return api.get<AuditLog>(`/${EntityName.AuditLog}/${id}`);
}

/**
 * Query params of the filters (`action[]`, `date[after]`... once serialized).
 */
export function auditLogParams(filters: AuditLogFilters): QueryParams {
    const params: QueryParams = {
        action: filters.actions?.length ? filters.actions : undefined,
        actorType: filters.actorType,
        objectType: filters.objectType,
        objectId: filters.objectId,
        userId: filters.userId,
        workspaceId: filters.workspaceId,
        correlationId: filters.correlationId,
    };
    if (filters.dateAfter || filters.dateBefore) {
        params.date = {
            after: filters.dateAfter,
            before: filters.dateBefore,
        };
    }

    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== undefined && v !== '')
    );
}
