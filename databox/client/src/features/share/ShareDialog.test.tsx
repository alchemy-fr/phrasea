import {beforeEach, describe, expect, it, vi} from 'vitest';
import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
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
    updateShare: vi.fn(),
    deleteShare: vi.fn(),
}));
vi.mock('@/lib/api/misc', () => api);
const toasts = vi.hoisted(() => ({
    success: vi.fn(),
    error: vi.fn(),
}));
vi.mock('sonner', () => ({toast: toasts}));
const misc = vi.hoisted(() => ({
    copyToClipboard: vi.fn(() => Promise.resolve()),
}));
vi.mock('@/lib/utils/misc', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...misc,
}));

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
const otherAssets = [
    {
        id: 'a2',
        name: 'Bravo',
        thumbnail: {file: {url: 'https://cdn/a2.jpg', type: 'image/jpeg'}},
    },
    {id: 'a3', name: 'Charlie', source: {type: 'application/pdf'}},
] as unknown as Asset[];

/** Opens a Radix dropdown menu from its trigger (keyboard, jsdom has no pointer events) */
function openMenu(trigger: HTMLElement) {
    fireEvent.keyDown(trigger, {key: 'Enter'});

    return screen.findAllByRole('menuitem');
}

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

function share(
    id: string,
    extra: Partial<Share> = {},
    assets: Asset[] = [asset]
): Share {
    return {
        id,
        name: `Link ${id}`,
        token: 't',
        assets,
        alternateUrls: [],
        createdAt: '2026-01-01T00:00:00Z',
        updatedAt: '2026-01-01T00:00:00Z',
        ...extra,
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
        api.createShare.mockImplementation(
            async (_ids: string[], data: Record<string, unknown>) =>
                share('s3', {
                    renditionDefinitions: (
                        data.renditionDefinitionIds as string[] | undefined
                    )?.map(id => ({id})),
                })
        );
    });

    it('offers the renditions of a public policy, all shared by default', async () => {
        api.getAssetShares.mockResolvedValue([
            share('s1', {renditionDefinitions: [{id: 'd1'}]}),
            share('s2'),
        ]);
        renderDialog();

        // Two links: the dialog opens in advanced mode, the restricted link shows its renditions
        const restricted = await screen.findByTestId('share-renditions');
        expect(restricted.textContent).toContain('Preview');
        expect(restricted.textContent).not.toContain('Thumbnail');
        expect(screen.getAllByTestId('share-renditions')).toHaveLength(1);

        // The new-link form comes before the existing links
        const createButton = screen.getByText('Create new share link');
        expect(
            createButton.compareDocumentPosition(restricted) &
                Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
        fireEvent.click(createButton);
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
            share('s1', {renditionDefinitions: [{id: 'd1'}]}),
            share('s2'),
        ]);
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

    it('lists the links of a larger set, picks the URL to copy and manages their assets', async () => {
        const alternateUrls = [
            {
                id: 'r1',
                name: 'preview',
                displayName: 'Preview',
                url: 'https://api/s/g/r/d1?asset=a1',
                assetId: 'a1',
            },
            {
                id: 'r2',
                name: 'preview',
                displayName: 'Preview',
                url: 'https://api/s/g/r/d1?asset=a2',
                assetId: 'a2',
            },
            {
                id: 'r3',
                name: 'thumbnail',
                displayName: 'Thumbnail',
                url: 'https://api/s/g/r/d2?asset=a1',
                assetId: 'a1',
            },
        ];
        const group = share('g', {name: 'Group', alternateUrls}, [
            asset,
            ...otherAssets,
        ]);
        api.getAssetShares.mockResolvedValue([
            group,
            // Holds another asset only: filtered out
            share('x', {}, otherAssets),
        ]);
        api.updateShare.mockImplementation(
            async (id: string, data: {assets: string[]}) => ({
                ...group,
                id,
                assets: group.assets.filter(a =>
                    data.assets.includes(`/assets/${a.id}`)
                ),
            })
        );
        renderDialog();

        const items = await screen.findAllByTestId('share-item');
        expect(items).toHaveLength(1);
        const item = items[0];
        expect(within(item).getByTestId('share-assets-count').textContent).toBe(
            '3 assets'
        );

        // One link: the share page, or a rendition of the selected asset picked from the label
        const link = within(item).getByTestId('share-link');
        expect(link.textContent).toContain('Asset page');
        expect(link.textContent).toContain('/g/');
        let menu = await openMenu(
            within(link).getByTestId('share-link-choice')
        );
        expect(menu.map(m => m.textContent)).toEqual([
            'Asset page',
            'Preview',
            'Thumbnail',
        ]);
        fireEvent.click(menu[2]);
        await waitFor(() => expect(link.textContent).toContain('Thumbnail'));
        expect(link.textContent).toContain('r/d2?asset=a1');

        // The assets of the link, with the URLs of their renditions
        const rows = within(item).getAllByTestId('share-asset-row');
        expect(rows.map(r => r.textContent)).toEqual([
            expect.stringContaining('Photo.jpg'),
            expect.stringContaining('Bravo'),
            expect.stringContaining('Charlie'),
        ]);
        expect(rows[1].querySelector('img')?.getAttribute('src')).toBe(
            'https://cdn/a2.jpg'
        );
        expect(
            (
                within(rows[2]).getByTestId(
                    'share-asset-copy'
                ) as HTMLButtonElement
            ).disabled
        ).toBe(true);
        menu = await openMenu(within(rows[1]).getByTestId('share-asset-copy'));
        expect(menu.map(m => m.textContent)).toEqual(['Preview']);
        fireEvent.click(menu[0]);
        await waitFor(() =>
            expect(misc.copyToClipboard).toHaveBeenCalledWith(
                'https://api/s/g/r/d1?asset=a2'
            )
        );

        // Removing an asset keeps the link listed while it holds the selection…
        fireEvent.click(within(rows[2]).getByTestId('share-asset-remove'));
        await waitFor(() =>
            expect(api.updateShare).toHaveBeenCalledWith('g', {
                assets: ['/assets/a1', '/assets/a2'],
            })
        );
        await waitFor(() =>
            expect(
                within(item).getByTestId('share-assets-count').textContent
            ).toBe('2 assets')
        );
        expect(within(item).getAllByTestId('share-asset-row')).toHaveLength(2);

        // The removal can be undone from the toast
        await waitFor(() =>
            expect(toasts.success).toHaveBeenCalledWith(
                'Asset removed from the link',
                expect.anything()
            )
        );
        // The "Link copied" toast of the copy above comes first
        toasts.success.mock.calls
            .find(c => c[0] === 'Asset removed from the link')![1]
            .action.onClick();
        await waitFor(() =>
            expect(api.updateShare).toHaveBeenLastCalledWith('g', {
                assets: ['/assets/a1', '/assets/a2', '/assets/a3'],
            })
        );
        await waitFor(() =>
            expect(
                within(item).getByTestId('share-assets-count').textContent
            ).toBe('3 assets')
        );
        // A non-image asset shows the icon of its kind
        expect(
            within(item)
                .getAllByTestId('share-asset-row')[2]
                .querySelector('img')
        ).toBeNull();

        // …and drops it once the selected asset is removed
        fireEvent.click(within(item).getAllByTestId('share-asset-remove')[0]);
        await waitFor(() =>
            expect(api.updateShare).toHaveBeenLastCalledWith('g', {
                assets: ['/assets/a2', '/assets/a3'],
            })
        );
        await waitFor(() =>
            expect(screen.queryByTestId('share-item')).toBeNull()
        );
    });

    it('reports a failed asset removal without offering to undo it', async () => {
        api.getAssetShares.mockResolvedValue([
            share('g', {name: 'Group'}, [asset, ...otherAssets]),
        ]);
        api.updateShare.mockRejectedValue(new Error('Forbidden'));
        renderDialog();

        const item = await screen.findByTestId('share-item');
        fireEvent.click(within(item).getAllByTestId('share-asset-remove')[2]);
        await waitFor(() =>
            expect(toasts.error).toHaveBeenCalledWith('Forbidden')
        );
        expect(toasts.success).not.toHaveBeenCalled();
        expect(within(item).getAllByTestId('share-asset-row')).toHaveLength(3);
    });

    it('edits the name and dates of a link', async () => {
        api.getAssetShares.mockResolvedValue([
            share('g', {name: 'Group', expiresAt: '2030-06-01T10:00:00.000Z'}, [
                asset,
                ...otherAssets,
            ]),
        ]);
        api.updateShare.mockImplementation(
            async (id: string, data: Record<string, unknown>) =>
                share(
                    id,
                    {
                        name: data.name as string,
                        expiresAt: data.expiresAt as string,
                    },
                    [asset, ...otherAssets]
                )
        );
        renderDialog();

        const item = (await screen.findAllByTestId('share-item'))[0];
        fireEvent.click(within(item).getByLabelText('Edit'));
        const name = within(item).getByLabelText(
            'Name / audience'
        ) as HTMLInputElement;
        expect(name.value).toBe('Group');
        const expires = within(item).getByLabelText(
            'Expires at'
        ) as HTMLInputElement;
        expect(expires.value).toMatch(/^2030-06-01T\d\d:\d\d$/);

        fireEvent.change(name, {target: {value: 'Press'}});
        fireEvent.change(expires, {target: {value: ''}});
        fireEvent.click(within(item).getByText('Save'));

        await waitFor(() => expect(api.updateShare).toHaveBeenCalledTimes(1));
        expect(api.updateShare.mock.calls[0]).toEqual([
            'g',
            {name: 'Press', startsAt: null, expiresAt: null},
        ]);
        await waitFor(() =>
            expect(within(item).queryByText('Save')).toBeNull()
        );
        expect(screen.getByText('Press')).toBeTruthy();
    });
});
