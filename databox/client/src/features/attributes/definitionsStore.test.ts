import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {AttributeDefinition} from '@/types/api';
import {
    seedDefinitions,
    slugOfSearchSlug,
    useDefinitionsStore,
} from './definitionsStore';

const {api} = vi.hoisted(() => ({
    api: {getAttributeDefinitionsPage: vi.fn()},
}));

vi.mock('@/lib/api/metadata', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/metadata')>()),
    getAttributeDefinitionsPage: api.getAttributeDefinitionsPage,
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

describe('slugOfSearchSlug', () => {
    it('drops the type, the cardinality and the locale', () => {
        expect(slugOfSearchSlug('title_text_s')).toBe('title');
        expect(slugOfSearchSlug('my_slug_date-time_m')).toBe('my_slug');
        expect(slugOfSearchSlug('description_text_s_fr')).toBe('description');
    });

    it('leaves out the built-ins', () => {
        expect(slugOfSearchSlug('@createdAt')).toBeUndefined();
    });
});

describe('ensure', () => {
    beforeEach(() => {
        api.getAttributeDefinitionsPage.mockReset();
        seedDefinitions({builtIn: []});
    });

    it('loads the keys asked for in the same tick with one request', async () => {
        serve([definition('a'), definition('b', 'title')]);
        const {ensure} = useDefinitionsStore.getState();

        await Promise.all([ensure({ids: ['a']}), ensure({ids: ['a', 'b']})]);

        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(1);
        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledWith({
            ids: ['a', 'b'],
            limit: 100,
        });
        expect(Object.keys(useDefinitionsStore.getState().byId)).toEqual([
            'a',
            'b',
        ]);

        // Resolved: no other request
        await ensure({ids: ['b']});
        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(1);
    });

    it('sends the keys by 50', async () => {
        serve([]);
        const slugs = Array.from({length: 120}, (_, i) => `s${i}`);

        await useDefinitionsStore.getState().ensure({slugs});

        const calls = api.getAttributeDefinitionsPage.mock.calls.map(
            ([params]) => (params as Params).slugs?.length
        );
        expect(calls).toEqual([50, 50, 20]);
    });

    it('asks for a missing key only once', async () => {
        serve([]);
        const {ensure} = useDefinitionsStore.getState();

        await ensure({slugs: ['gone'], ids: ['x']});
        await ensure({slugs: ['gone'], ids: ['x']});

        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(2);
        expect(useDefinitionsStore.getState().resolved).toMatchObject({
            'slug:gone': true,
            'id:x': true,
        });
    });

    it('asks again after an error', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        api.getAttributeDefinitionsPage.mockRejectedValueOnce(
            new Error('Down')
        );
        const {ensure} = useDefinitionsStore.getState();

        await ensure({ids: ['a']});
        expect(useDefinitionsStore.getState().byId.a).toBeUndefined();
        expect(warn).toHaveBeenCalled();

        serve([definition('a')]);
        await ensure({ids: ['a']});
        expect(useDefinitionsStore.getState().byId.a?.slug).toBe('a');
        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(2);
        warn.mockRestore();
    });

    it('never asks for the built-ins', async () => {
        await useDefinitionsStore
            .getState()
            .ensure({ids: ['@createdAt'], slugs: ['@workspace']});

        expect(api.getAttributeDefinitionsPage).not.toHaveBeenCalled();
    });

    it('asks again for the definitions of an invalidated workspace', async () => {
        serve([definition('a')]);
        const {ensure, invalidateWorkspace} = useDefinitionsStore.getState();
        await ensure({ids: ['a']});

        invalidateWorkspace('ws');
        await ensure({ids: ['a']});

        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledTimes(2);
    });
});
