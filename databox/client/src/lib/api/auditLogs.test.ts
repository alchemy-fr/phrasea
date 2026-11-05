import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AuditLogActorType} from '@/types/api';

const get = vi.fn();

import {auditLogParams, getAuditLogs} from './auditLogs';
import {buildSearchParams} from './http';

vi.mock('./http', async importOriginal => {
    const actual = await importOriginal<typeof import('./http')>();

    return {...actual, api: {get: (...args: unknown[]) => get(...args)}};
});

describe('getAuditLogs', () => {
    beforeEach(() => {
        get.mockReset();
        get.mockResolvedValue({
            totalItems: 1,
            member: [{id: 'l1'}],
            view: {next: '/audit-logs?page=2&action%5B%5D=asset_moved'},
        });
    });

    it('sends the filters as query params', async () => {
        const page = await getAuditLogs({
            actions: ['asset_moved', 'object_updated'],
            actorType: AuditLogActorType.User,
            workspaceId: 'ws-1',
            dateAfter: '2026-01-01T00:00:00.000Z',
        });

        expect(get).toHaveBeenCalledWith('/audit-logs', {
            params: {
                action: ['asset_moved', 'object_updated'],
                actorType: 'user',
                workspaceId: 'ws-1',
                date: {
                    after: '2026-01-01T00:00:00.000Z',
                    before: undefined,
                },
            },
        });
        expect(page.items.map(i => i.id)).toEqual(['l1']);
        expect(page.next).toBe('/audit-logs?page=2&action%5B%5D=asset_moved');
    });

    it('follows the next link instead of the filters', async () => {
        await getAuditLogs({objectId: 'ignored'}, '/audit-logs?page=2');

        expect(get).toHaveBeenCalledWith('/audit-logs?page=2');
    });
});

describe('auditLogParams', () => {
    it('serializes the way the API reads them', () => {
        const params = buildSearchParams(
            auditLogParams({
                actions: ['asset_moved'],
                objectId: 'a-1',
                correlationId: '',
                dateBefore: '2026-02-01T00:00:00.000Z',
            })
        );

        expect(params.getAll('action[]')).toEqual(['asset_moved']);
        expect(params.get('objectId')).toBe('a-1');
        expect(params.get('date[before]')).toBe('2026-02-01T00:00:00.000Z');
        expect(params.has('date[after]')).toBe(false);
        expect(params.has('correlationId')).toBe(false);
    });

    it('leaves out no filter at all', () => {
        expect(auditLogParams({})).toEqual({});
        expect(auditLogParams({actions: []})).toEqual({});
    });
});
