import {describe, expect, it, vi} from 'vitest';
import {act, render, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {AssetTypeFilter, type AttributeDefinition} from '@/types/api';
import {NO_LOCALE} from '@/lib/utils/locale';
import {useAttributeEditor} from './useAttributeEditor';

const {api} = vi.hoisted(() => ({
    api: {getAttributeDefinitionsPage: vi.fn()},
}));

vi.mock('@/lib/api/metadata', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/metadata')>()),
    getAttributeDefinitionsPage: api.getAttributeDefinitionsPage,
}));

function definition(id: string): AttributeDefinition {
    return {
        id,
        slug: id,
        name: id,
        type: 'text',
        workspace: '/workspaces/ws',
        editable: true,
        editableInGui: true,
        canEdit: true,
        enabled: true,
    } as unknown as AttributeDefinition;
}

type Editor = ReturnType<typeof useAttributeEditor>;

function Harness({onRender}: {onRender: (editor: Editor) => void}) {
    onRender(
        useAttributeEditor({workspaceId: 'ws', target: AssetTypeFilter.Asset})
    );

    return null;
}

describe('useAttributeEditor', () => {
    it('keeps the edits when the next page of definitions comes', async () => {
        api.getAttributeDefinitionsPage.mockImplementation(
            async ({url}: {url?: string}) =>
                url
                    ? {items: [definition('d2')], total: 2}
                    : {items: [definition('d1')], total: 2, next: '/page-2'}
        );
        const result = {current: undefined as unknown as Editor};
        render(
            <QueryClientProvider
                client={
                    new QueryClient({defaultOptions: {queries: {retry: false}}})
                }
            >
                <Harness onRender={e => (result.current = e)} />
            </QueryClientProvider>
        );
        await waitFor(() => expect(result.current.definitions.d1).toBeTruthy());
        expect(api.getAttributeDefinitionsPage).toHaveBeenCalledWith(
            expect.objectContaining({workspaceId: 'ws', limit: 100}),
            expect.anything()
        );

        act(() => {
            result.current.onChange('d1', NO_LOCALE, {id: 'n1', value: 'Hi'});
        });
        expect(result.current.hasMore).toBe(true);

        act(() => result.current.loadMore());
        await waitFor(() => expect(result.current.definitions.d2).toBeTruthy());

        expect(result.current.attributes.d1[NO_LOCALE]).toEqual({
            id: 'n1',
            value: 'Hi',
        });
        expect(result.current.attributes.d2).toEqual({});
        expect(result.current.dirty).toBe(true);
        expect(result.current.hasMore).toBe(false);
    });
});
