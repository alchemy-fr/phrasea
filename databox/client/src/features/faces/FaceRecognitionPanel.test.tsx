import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
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
        getIntegrationData: vi.fn(),
        runIntegrationAction: vi.fn(),
    },
}));

vi.mock('@/lib/api/integrations', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/lib/api/integrations')>();

    return {...actual, ...api};
});

const asset = {
    id: 'asset-1',
    name: 'Group photo',
    preview: {file: {url: 'https://cdn.test/preview.jpg'}},
} as unknown as Asset;

const integration = {
    id: 'int-1',
    integration: 'core.face_recognition',
    capabilities: {interact: true},
} as unknown as WorkspaceIntegration;

const summary = {
    faces: [
        {
            id: 'face-1',
            box: {x: 0.1, y: 0.1, w: 0.2, h: 0.3},
            confidence: 0.9,
            identity: null,
            identityConfidence: null,
            identityOrigin: null,
        },
        {
            id: 'face-2',
            box: {x: 0.6, y: 0.1, w: 0.2, h: 0.3},
            confidence: 0.9,
            identity: 'Alice',
            identityConfidence: 0.75,
            identityOrigin: 'auto',
        },
    ],
};

function renderPanel() {
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
                        asset={asset}
                        file={{id: 'file-1'} as any}
                    />
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('FaceRecognitionPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        realtime.handlers = {};
    });

    it('reads the faces stored on the asset and names one', async () => {
        api.getIntegrationData.mockResolvedValue({
            total: 1,
            items: [{id: 'd1', name: 'faces', value: JSON.stringify(summary)}],
        });
        api.runIntegrationAction.mockResolvedValue({
            asset: 'asset-1',
            faces: [
                {...summary.faces[0], identity: 'Bob', identityOrigin: 'user'},
                summary.faces[1],
            ],
        });
        renderPanel();

        const cards = await screen.findAllByTestId('face-card');
        expect(cards).toHaveLength(2);
        expect(api.getIntegrationData).toHaveBeenCalledWith(
            'int-1',
            {type: 'asset', id: 'asset-1'},
            expect.anything()
        );
        expect(screen.getByText('Recognized · 75%')).toBeTruthy();
        expect(
            realtime.handlers['asset-asset-1|integration:core.face_recognition']
        ).toBeTypeOf('function');

        const input = cards[0].querySelector(
            '[data-testid=face-name]'
        ) as HTMLInputElement;
        fireEvent.change(input, {target: {value: 'Bob'}});
        fireEvent.submit(cards[0]);

        await waitFor(() =>
            expect(api.runIntegrationAction).toHaveBeenCalledWith(
                'int-1',
                'identify',
                {faceId: 'face-1', identity: 'Bob'}
            )
        );
        await waitFor(() => expect(cards[0].textContent).toContain('Named'));
    });

    it('runs the detection on the asset', async () => {
        api.getIntegrationData.mockResolvedValue({total: 0, items: []});
        api.runIntegrationAction.mockResolvedValue({});
        renderPanel();

        expect(
            await screen.findByText(
                'Faces have not been detected on this asset yet'
            )
        ).toBeTruthy();
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
