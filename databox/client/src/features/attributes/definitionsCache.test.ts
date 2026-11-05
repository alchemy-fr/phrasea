import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import type {AttributeDefinition, BuiltInAttribute} from '@/types/api';
import {seedDefinitions, useDefinitionsStore} from './definitionsStore';
import {
    clearDefinitionsCache,
    DEFINITIONS_CACHE_KEY,
    hydrateDefinitions,
    resetDefinitionsCache,
} from './definitionsCache';

const {api} = vi.hoisted(() => ({
    api: {
        getAttributeDefinitionsPage: vi.fn(),
        getBuiltInAttributes: vi.fn(),
    },
}));

vi.mock('@/lib/api/metadata', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/metadata')>()),
    getAttributeDefinitionsPage: api.getAttributeDefinitionsPage,
    getBuiltInAttributes: api.getBuiltInAttributes,
}));

function definition(id: string, slug = id): AttributeDefinition {
    return {
        id,
        slug,
        name: slug,
        searchSlug: `${slug}_text_s`,
        workspace: '/workspaces/ws',
        enabled: true,
    } as unknown as AttributeDefinition;
}

const createdAt = {
    id: '@createdAt',
    slug: '@createdAt',
    searchSlug: '@createdAt',
    name: 'Created at',
    builtIn: true,
    enabled: true,
} as unknown as BuiltInAttribute;

type Params = {ids?: string[]; slugs?: string[]; url?: string};

/** Answers the definitions of the ids / slugs asked for, if they exist */
function serve(known: AttributeDefinition[]) {
    api.getAttributeDefinitionsPage.mockImplementation(
        async ({ids, slugs}: Params) => {
            const items = known.filter(
                d => ids?.includes(d.id) || slugs?.includes(d.slug)
            );

            return {items, total: items.length};
        }
    );
}

function store(
    entry: Partial<{
        v: unknown;
        user: string;
        locale: string;
        byId: Record<string, AttributeDefinition>;
        builtIn: BuiltInAttribute[];
    }> = {}
) {
    sessionStorage.setItem(
        DEFINITIONS_CACHE_KEY,
        JSON.stringify({
            v: 1,
            user: 'alice',
            locale: 'en',
            byId: {a: definition('a'), b: definition('b')},
            builtIn: [createdAt],
            ...entry,
        })
    );
}

function stored() {
    const raw = sessionStorage.getItem(DEFINITIONS_CACHE_KEY);

    return raw ? JSON.parse(raw) : null;
}

const alice = {user: 'alice', locale: 'en'};

describe('definitions cache', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        resetDefinitionsCache();
        sessionStorage.clear();
        api.getAttributeDefinitionsPage.mockReset();
        api.getBuiltInAttributes.mockReset();
        api.getBuiltInAttributes.mockResolvedValue([createdAt]);
        serve([]);
        seedDefinitions({});
        useDefinitionsStore.setState({builtIn: [], builtInLoaded: false});
    });

    afterEach(() => {
        resetDefinitionsCache();
        vi.useRealTimers();
    });

    it('restores the entry of the user and language, then revalidates it', async () => {
        store();
        serve([definition('a')]);

        const revalidated = hydrateDefinitions(alice);

        const state = useDefinitionsStore.getState();
        expect(Object.keys(state.byId)).toEqual(['a', 'b']);
        expect(state.builtIn.map(b => b.id)).toEqual(['@createdAt']);
        expect(state.resolved).toEqual({});
        expect(state.builtInLoaded).toBe(false);

        await revalidated;

        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(1);
        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledWith({
            ids: ['a', 'b'],
            limit: 100,
        });
        expect(api.getBuiltInAttributes).toHaveBeenCalledTimes(1);
        // Gone (deleted or no longer visible) meanwhile
        expect(Object.keys(useDefinitionsStore.getState().byId)).toEqual(['a']);
    });

    it.each([
        ['another user', () => store({user: 'bob'})],
        ['another language', () => store({locale: 'fr'})],
        ['another version', () => store({v: 0})],
        [
            'invalid JSON',
            () => sessionStorage.setItem(DEFINITIONS_CACHE_KEY, '{oops'),
        ],
        [
            'a malformed entry',
            () => sessionStorage.setItem(DEFINITIONS_CACHE_KEY, '{"v":1}'),
        ],
    ])('drops the entry of %s', async (_, setup) => {
        setup();
        seedDefinitions({definitions: [definition('x')]});
        const before = useDefinitionsStore.getState();

        await hydrateDefinitions(alice);

        expect(sessionStorage.getItem(DEFINITIONS_CACHE_KEY)).toBeNull();
        const after = useDefinitionsStore.getState();
        expect(after.byId).toBe(before.byId);
        expect(after.builtIn).toBe(before.builtIn);
        expect(api.getAttributeDefinitionsPage).not.toHaveBeenCalled();
    });

    it('keeps the definitions already in memory', async () => {
        store({
            byId: {a: definition('a', 'stale'), b: definition('b')},
        });
        seedDefinitions({definitions: [definition('a', 'fresh')]});
        serve([definition('a', 'fresh'), definition('b')]);

        await hydrateDefinitions(alice);

        const {byId} = useDefinitionsStore.getState();
        expect(byId.a?.slug).toBe('fresh');
        expect(byId.b?.slug).toBe('b');
        // `a` is resolved already
        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledWith({
            ids: ['b'],
            limit: 100,
        });
    });

    it('is hydrated once per user and language', async () => {
        store();

        await hydrateDefinitions(alice);
        await hydrateDefinitions(alice);

        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(1);
    });

    it('writes the store a moment after a change', async () => {
        await hydrateDefinitions({user: 'alice', locale: 'fr'});

        useDefinitionsStore.getState().ingest([definition('a')]);
        expect(stored()).toBeNull();

        vi.advanceTimersByTime(999);
        expect(stored()).toBeNull();
        useDefinitionsStore.getState().ingest([definition('b')]);
        vi.advanceTimersByTime(999);
        expect(stored()).toBeNull();

        vi.advanceTimersByTime(1);
        expect(stored()).toMatchObject({
            v: 1,
            user: 'alice',
            locale: 'fr',
            byId: {a: {id: 'a'}, b: {id: 'b'}},
            builtIn: [],
        });
    });

    it('writes right away when the page is hidden', async () => {
        await hydrateDefinitions(alice);

        useDefinitionsStore.getState().ingest([definition('a')]);
        window.dispatchEvent(new Event('pagehide'));

        expect(stored()?.byId).toHaveProperty('a');
    });

    it('ignores a full storage', async () => {
        await hydrateDefinitions(alice);
        const setItem = vi
            .spyOn(Object.getPrototypeOf(sessionStorage), 'setItem')
            .mockImplementation(() => {
                throw new DOMException('Full', 'QuotaExceededError');
            });

        useDefinitionsStore.getState().ingest([definition('a')]);

        expect(() => vi.advanceTimersByTime(1000)).not.toThrow();
        expect(setItem).toHaveBeenCalled();
        setItem.mockRestore();
    });

    it('drops the entry and revalidates everything on a change of language', async () => {
        store();
        serve([definition('a'), definition('b')]);
        await hydrateDefinitions(alice);
        vi.advanceTimersByTime(1000);
        expect(stored()?.locale).toBe('en');
        api.getAttributeDefinitionsPage.mockClear();
        api.getBuiltInAttributes.mockClear();

        const revalidated = hydrateDefinitions({user: 'alice', locale: 'fr'});
        expect(sessionStorage.getItem(DEFINITIONS_CACHE_KEY)).toBeNull();
        expect(useDefinitionsStore.getState().resolved).toEqual({});
        await revalidated;

        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledWith({
            ids: ['a', 'b'],
            limit: 100,
        });
        expect(api.getBuiltInAttributes).toHaveBeenCalledTimes(1);

        useDefinitionsStore.getState().ingest([definition('c')]);
        vi.advanceTimersByTime(1000);
        expect(stored()?.locale).toBe('fr');
    });

    it('stops persisting once cleared', async () => {
        await hydrateDefinitions(alice);
        useDefinitionsStore.getState().ingest([definition('a')]);

        clearDefinitionsCache();
        vi.advanceTimersByTime(1000);
        window.dispatchEvent(new Event('pagehide'));

        expect(sessionStorage.getItem(DEFINITIONS_CACHE_KEY)).toBeNull();
    });
});
