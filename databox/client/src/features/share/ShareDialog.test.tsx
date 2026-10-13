import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@/components/modals/ModalProvider';
import type {Asset, RenditionDefinition, Share} from '@/types/api';
import {ShareDialog} from './ShareDialog';

const api = vi.hoisted(() => ({
    getAssetShares: vi.fn<() => Promise<Share[]>>(),
    getRenditionDefinitions: vi.fn(),
    createShare: vi.fn(),
    deleteShare: vi.fn(),
}));
vi.mock('@/lib/api/misc', () => api);

// Used by the Radix checkbox, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

const asset = {
    id: 'a1',
    name: 'Photo.jpg',
    workspace: {id: 'w1'},
} as unknown as Asset;

function definition(
    id: string,
    name: string,
    isPublic: boolean
): RenditionDefinition {
    return {
        id,
        name,
        displayName: name,
        policy: {id: `p-${id}`, name: 'policy', public: isPublic},
    } as unknown as RenditionDefinition;
}

function share(id: string, renditionDefinitions?: {id: string}[]): Share {
    return {
        id,
        name: `Link ${id}`,
        token: 't',
        assets: [asset],
        alternateUrls: [],
        createdAt: '2026-01-01T00:00:00Z',
        updatedAt: '2026-01-01T00:00:00Z',
        renditionDefinitions,
    } as unknown as Share;
}

function renderDialog() {
    act(() => {
        render(
            <QueryClientProvider
                client={
                    new QueryClient({
                        defaultOptions: {queries: {retry: false}},
                    })
                }
            >
                <I18nextProvider i18n={createI18n('en')}>
                    <TooltipPrimitive.Provider>
                        <ModalProvider>
                            <ShareDialog
                                open
                                onOpenChange={() => undefined}
                                assets={[asset]}
                            />
                        </ModalProvider>
                    </TooltipPrimitive.Provider>
                </I18nextProvider>
            </QueryClientProvider>
        );
    });
}

describe('ShareDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getRenditionDefinitions.mockResolvedValue({
            items: [
                definition('d1', 'Preview', true),
                definition('d2', 'Thumbnail', true),
                definition('d3', 'Original', false),
            ],
            total: 3,
        });
    });

    it('offers the renditions of a public policy, all shared by default', async () => {
        api.getAssetShares.mockResolvedValue([
            share('s1', [{id: 'd1'}]),
            share('s2'),
        ]);
        api.createShare.mockImplementation(
            async (_ids: string[], data: Record<string, unknown>) =>
                share(
                    's3',
                    (data.renditionDefinitionIds as string[])?.map(id => ({id}))
                )
        );
        renderDialog();

        // Two links: the dialog opens in advanced mode, the restricted link shows its renditions
        const restricted = await screen.findByTestId('share-renditions');
        expect(restricted.textContent).toContain('Preview');
        expect(restricted.textContent).not.toContain('Thumbnail');
        expect(screen.getAllByTestId('share-renditions')).toHaveLength(1);

        fireEvent.click(screen.getByText('Create new share link'));
        const list = await screen.findByTestId('share-create-renditions');
        const boxes = list.querySelectorAll('button[role="checkbox"]');
        expect(boxes).toHaveLength(2);
        expect(list.textContent).not.toContain('Original');
        boxes.forEach(b =>
            expect(b.getAttribute('data-state')).toBe('checked')
        );

        // Every rendition checked: no selection is sent
        fireEvent.click(screen.getByText('Create'));
        await waitFor(() => expect(api.createShare).toHaveBeenCalledTimes(1));
        expect(
            api.createShare.mock.calls[0][1].renditionDefinitionIds
        ).toBeUndefined();
    });

    it('sends the checked renditions and requires at least one', async () => {
        api.getAssetShares.mockResolvedValue([
            share('s1', [{id: 'd1'}]),
            share('s2'),
        ]);
        api.createShare.mockImplementation(
            async (_ids: string[], data: Record<string, unknown>) =>
                share(
                    's3',
                    (data.renditionDefinitionIds as string[])?.map(id => ({id}))
                )
        );
        renderDialog();
        fireEvent.click(await screen.findByText('Create new share link'));
        const list = await screen.findByTestId('share-create-renditions');
        const [preview, thumbnail] = [
            ...list.querySelectorAll('button[role="checkbox"]'),
        ];

        fireEvent.click(preview);
        fireEvent.click(thumbnail);
        expect(screen.getByText('Select at least one rendition')).toBeTruthy();
        expect((screen.getByText('Create') as HTMLButtonElement).disabled).toBe(
            true
        );

        fireEvent.click(thumbnail);
        fireEvent.click(screen.getByText('Create'));
        await waitFor(() => expect(api.createShare).toHaveBeenCalledTimes(1));
        expect(api.createShare.mock.calls[0][1].renditionDefinitionIds).toEqual(
            ['d2']
        );

        // The new link is listed with its renditions
        await waitFor(() =>
            expect(screen.getAllByTestId('share-renditions')).toHaveLength(2)
        );
    });
});
