import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {Asset, AssetAnnotation, WorkspaceIntegration} from '@/types/api';
import {useFaceAnnotationControl} from './FaceAnnotationControl';

const {api, integrations} = vi.hoisted(() => ({
    api: {
        patchAssetAttribute: vi.fn(),
        getAttributeEntities: vi.fn(),
        loadAttributeEntity: vi.fn(),
    },
    integrations: {items: [] as WorkspaceIntegration[]},
}));

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({isAuthenticated: true}),
}));
vi.mock('@/features/integrations/FileIntegrations', () => ({
    useFileIntegrations: () => integrations.items,
}));
vi.mock('@/lib/api/assets', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/assets')>();

    return {...actual, patchAssetAttribute: api.patchAssetAttribute};
});
vi.mock('@/lib/api/metadata', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/metadata')>();

    return {
        ...actual,
        getAttributeEntities: api.getAttributeEntities,
        loadAttributeEntity: api.loadAttributeEntity,
    };
});

const integration = {
    id: 'int-1',
    integration: 'core.face_recognition',
    workspace: {id: 'ws'},
    capabilities: {use: true, interact: true},
    config: {entityList: 'list-1', attribute: 'persons'},
} as unknown as WorkspaceIntegration;

const asset = {
    id: 'asset-1',
    capabilities: {edit: true},
    attributes: [
        {
            id: 'attr-1',
            definition: {id: 'def-1', slug: 'persons'},
            value: {id: 'unknown-1', value: null, status: 1},
            origin: 'machine',
            assetAnnotations: [{type: 'face', x: 0.1, y: 0.1, w: 0.2, h: 0.3}],
        },
    ],
} as unknown as Asset;

const face: AssetAnnotation = {
    id: 'attr-1-0',
    type: 'face',
    source: {attributeId: 'attr-1', definitionId: 'def-1', index: 0},
};

function Harness({
    asset,
    annotation,
}: {
    asset: Asset;
    annotation: AssetAnnotation;
}) {
    const renderControl = useFaceAnnotationControl(asset, {
        id: 'file-1',
    } as any);

    return (
        <div data-testid="harness">
            {renderControl
                ? (renderControl(annotation) ?? 'no control')
                : 'no renderer'}
        </div>
    );
}

function renderHarness(annotation: AssetAnnotation, a: Asset = asset) {
    return render(
        <QueryClientProvider
            client={
                new QueryClient({defaultOptions: {queries: {retry: false}}})
            }
        >
            <I18nextProvider i18n={createI18n('en')}>
                <Harness asset={a} annotation={annotation} />
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

describe('useFaceAnnotationControl', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        integrations.items = [integration];
    });

    it('names the face of a shape from its panel', async () => {
        api.getAttributeEntities.mockResolvedValue({
            items: [{id: 'bob', value: 'Bob', status: 0}],
        });
        api.patchAssetAttribute.mockResolvedValue({});
        renderHarness(face);

        fireEvent.click(screen.getByRole('combobox'));
        fireEvent.click(await screen.findByText('Bob'));

        await waitFor(() =>
            expect(api.patchAssetAttribute).toHaveBeenCalledWith('attr-1', {
                value: 'bob',
            })
        );
    });

    it('leaves the other shapes their name', () => {
        renderHarness({
            id: 'x',
            type: 'rect',
            source: {attributeId: 'attr-1', definitionId: 'def-1', index: 1},
        });

        expect(screen.getByTestId('harness').textContent).toBe('no control');
    });

    it('offers nothing to whoever may not set the persons', () => {
        integrations.items = [
            {
                ...integration,
                capabilities: {use: true, interact: false},
            } as unknown as WorkspaceIntegration,
        ];
        renderHarness(face);

        expect(screen.getByTestId('harness').textContent).toBe('no renderer');
    });
});
