import {beforeEach, describe, expect, it, vi} from 'vitest';
import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import type {Asset, WorkspaceIntegration} from '@/types/api';
import {FaceRecognitionPanel} from './FaceRecognitionPanel';

vi.mock('next/link', () => ({
    default: ({href, children, ...props}: any) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
}));

const realtime = vi.hoisted(() => ({
    handlers: {} as Record<string, (data: unknown) => void>,
}));

vi.mock('@/lib/realtime/RealtimeProvider', () => ({
    useChannelEvent: (
        channel: string,
        event: string,
        callback: (data: unknown) => void
    ) => {
        realtime.handlers[`${channel}|${event}`] = callback;
    },
}));

const {api} = vi.hoisted(() => ({
    api: {
        runIntegrationAction: vi.fn(),
        patchAssetAttribute: vi.fn(),
        deleteAssetAttribute: vi.fn(),
        getAttributeEntities: vi.fn(),
        getAttributeEntity: vi.fn(),
        putAttributeEntity: vi.fn(),
    },
}));

vi.mock('@/lib/api/integrations', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/lib/api/integrations')>();

    return {...actual, runIntegrationAction: api.runIntegrationAction};
});
vi.mock('@/lib/api/assets', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/assets')>();

    return {
        ...actual,
        patchAssetAttribute: api.patchAssetAttribute,
        deleteAssetAttribute: api.deleteAssetAttribute,
    };
});
vi.mock('@/lib/api/metadata', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/metadata')>();

    return {
        ...actual,
        getAttributeEntities: api.getAttributeEntities,
        getAttributeEntity: api.getAttributeEntity,
        putAttributeEntity: api.putAttributeEntity,
    };
});

const integration = {
    id: 'int-1',
    integration: 'core.face_recognition',
    workspace: {id: 'ws'},
    capabilities: {use: true, interact: true},
    config: {
        entityList: 'list-1',
        attribute: 'persons',
        attributeDefinition: 'def-1',
    },
} as unknown as WorkspaceIntegration;

const asset = {
    id: 'asset-1',
    name: 'Group photo',
    preview: {file: {url: 'https://cdn.test/preview.jpg'}},
    capabilities: {edit: true},
    attributes: [
        {
            id: 'attr-1',
            definition: {id: 'def-1', slug: 'persons'},
            value: {id: 'unknown-1', value: null, status: 1},
            origin: 'machine',
            confidence: 0.9,
            assetAnnotations: [{type: 'face', x: 0.1, y: 0.1, w: 0.2, h: 0.3}],
        },
        {
            id: 'attr-2',
            definition: {id: 'def-1', slug: 'persons'},
            value: {id: 'alice', value: 'Alice', status: 0},
            origin: 'machine',
            confidence: 0.75,
            assetAnnotations: [{type: 'face', x: 0.6, y: 0.1, w: 0.2, h: 0.3}],
        },
        {
            id: 'attr-3',
            definition: {id: 'def-2', slug: 'keywords'},
            value: 'sea',
            origin: 'human',
        },
    ],
} as unknown as Asset;

function renderPanel(props: Partial<{asset: Asset}> = {}) {
    const i18n = createI18n('en');
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={i18n}>
                <TooltipPrimitive.Provider>
                    <FaceRecognitionPanel
                        integration={integration}
                        asset={props.asset ?? asset}
                        file={{id: 'file-1'} as any}
                    />
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

// cmdk needs them, jsdom has none
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => {};

describe('FaceRecognitionPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        realtime.handlers = {};
    });

    it('reads the faces from the attributes of the asset and confirms one', async () => {
        api.patchAssetAttribute.mockResolvedValue({
            ...(asset.attributes[1] as object),
            origin: 'human',
        });
        renderPanel();

        const cards = await screen.findAllByTestId('face-card');
        expect(cards).toHaveLength(2);
        expect(screen.getByText('Unnamed')).toBeTruthy();
        expect(screen.getByText('Recognized · 75%')).toBeTruthy();
        expect(
            realtime.handlers['asset-asset-1|integration:core.face_recognition']
        ).toBeTypeOf('function');

        fireEvent.click(
            cards[1].querySelector('[data-testid=face-confirm]') as HTMLElement
        );
        await waitFor(() =>
            expect(api.patchAssetAttribute).toHaveBeenCalledWith('attr-2', {
                value: 'alice',
            })
        );
    });

    it('names an unknown face with a named person only', async () => {
        api.getAttributeEntities.mockResolvedValue({
            items: [{id: 'bob', value: 'Bob', status: 0}],
        });
        api.patchAssetAttribute.mockResolvedValue({
            ...(asset.attributes[0] as object),
            origin: 'human',
        });
        renderPanel();

        const cards = await screen.findAllByTestId('face-card');
        fireEvent.click(within(cards[0]).getByRole('combobox'));
        fireEvent.click(await screen.findByText('Bob'));

        expect(api.getAttributeEntities).toHaveBeenCalledWith(
            expect.objectContaining({list: 'list-1', status: 0})
        );
        await waitFor(() =>
            expect(api.patchAssetAttribute).toHaveBeenCalledWith('attr-1', {
                value: 'bob',
            })
        );
    });

    it('names the unknown person of a face', async () => {
        api.getAttributeEntities.mockResolvedValue({items: []});
        api.putAttributeEntity.mockResolvedValue({
            id: 'unknown-1',
            value: 'Carol',
            status: 0,
        });
        renderPanel();

        const cards = await screen.findAllByTestId('face-card');
        fireEvent.click(within(cards[0]).getByRole('combobox'));
        fireEvent.change(await screen.findByPlaceholderText('Search…'), {
            target: {value: 'Carol'},
        });
        fireEvent.click(await screen.findByText('New person « Carol »'));

        await waitFor(() =>
            expect(api.putAttributeEntity).toHaveBeenCalledWith('unknown-1', {
                value: 'Carol',
                status: 0,
            })
        );
        expect(api.patchAssetAttribute).not.toHaveBeenCalled();
    });

    it('runs the detection on the asset', async () => {
        api.runIntegrationAction.mockResolvedValue({});
        renderPanel({asset: {...asset, attributes: []} as Asset});

        expect(await screen.findByText('No face detected')).toBeTruthy();
        fireEvent.click(screen.getByTestId('faces-detect'));
        await waitFor(() =>
            expect(api.runIntegrationAction).toHaveBeenCalledWith(
                'int-1',
                'analyze',
                {assetId: 'asset-1'}
            )
        );
    });
});
