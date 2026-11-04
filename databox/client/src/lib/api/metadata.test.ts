import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();

vi.mock('./http', () => ({api: {get: (...args: unknown[]) => get(...args)}}));

import {loadAttributeEntity} from './metadata';

const uuid = (n: number) =>
    `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const rejected = uuid(99);

describe('loadAttributeEntity', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        get.mockReset();
        get.mockImplementation(
            (url: string, options?: {params: {ids: string[]}}) =>
                Promise.resolve(
                    url === '/attribute-entities'
                        ? {
                              totalItems: 0,
                              member: options!.params.ids
                                  .filter(id => id !== rejected)
                                  .map(id => ({id, value: `Value ${id}`})),
                          }
                        : {id: url.split('/').pop(), value: 'Single'}
                )
        );
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('fetches the entities requested together in one listing', async () => {
        const ids = [uuid(1), uuid(2), uuid(3)];

        const entities = Promise.all(ids.map(loadAttributeEntity));
        await vi.runAllTimersAsync();

        expect((await entities).map(e => e.id)).toEqual(ids);
        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0][1].params).toMatchObject({ids, limit: 3});
    });

    it('fetches one by one the values the listing leaves out', async () => {
        const entities = Promise.all([
            loadAttributeEntity(uuid(1)),
            loadAttributeEntity(rejected),
            loadAttributeEntity('not-a-uuid'),
        ]);
        await vi.runAllTimersAsync();

        expect((await entities).map(e => e.value)).toEqual([
            `Value ${uuid(1)}`,
            'Single',
            'Single',
        ]);
        expect(get.mock.calls.map(c => c[0]).sort()).toEqual([
            '/attribute-entities',
            `/attribute-entities/${rejected}`,
            '/attribute-entities/not-a-uuid',
        ]);
        const listing = get.mock.calls.find(
            c => c[0] === '/attribute-entities'
        )!;
        expect(listing[1].params.ids).toEqual([uuid(1), rejected]);
    });
});
