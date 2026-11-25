import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {RoutePathProvider} from '@/lib/navigation/routePath';
import type {RenditionDefinition, Workspace} from '@/types/api';
import {AssetType, RenditionBuildMode} from '@/types/api';
import {RenditionDefinitionsTab} from './RenditionDefinitionsTab';

vi.mock('next/navigation', () => ({
    usePathname: () => '/workspaces/ws/manage/renditions',
}));

vi.mock('sonner', () => ({toast: {success: vi.fn(), error: vi.fn()}}));

// Ace is not for jsdom: a plain textarea stands in for the YAML editor
vi.mock('@/components/form/code/CodeEditor', () => ({
    CodeEditor: ({
        value,
        onChange,
    }: {
        value: string;
        onChange: (v: string) => void;
    }) => <textarea value={value} onChange={e => onChange(e.target.value)} />,
}));

const definition = {
    '@id': '/rendition-definitions/r1',
    'id': 'r1',
    'name': 'preview',
    'displayName': 'preview',
    'translations': {name: {fr: 'Aperçu'}},
    'policy': null,
    'parent': null,
    'workspace': '/workspaces/ws',
    'definition': '',
    'buildMode': RenditionBuildMode.Custom,
    'substitutable': true,
    'writeMetadata': false,
    'priority': 0,
    'target': AssetType.Asset,
} as unknown as RenditionDefinition;

const api = vi.hoisted(() => ({
    getRenditionDefinitions: vi.fn(),
    getRenditionDefinition: vi.fn(),
    getRenditionPolicies: vi.fn(),
    getRenditionBuildReference: vi.fn(),
    putRenditionDefinition: vi.fn(),
    postRenditionDefinition: vi.fn(),
}));

vi.mock('@/lib/api/misc', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/misc')>()),
    ...api,
}));

beforeAll(() => {
    globalThis.IntersectionObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof IntersectionObserver;
});

function renderTab(enabledLocales?: string[]) {
    const workspace = {
        id: 'ws',
        name: 'Workspace',
        enabledLocales,
    } as unknown as Workspace;

    return render(
        <QueryClientProvider
            client={
                new QueryClient({defaultOptions: {queries: {retry: false}}})
            }
        >
            <I18nextProvider i18n={createI18n('en')}>
                <ModalProvider>
                    <RoutePathProvider
                        value={{segments: ['r1'], navigate: () => undefined}}
                    >
                        <RenditionDefinitionsTab
                            workspace={workspace}
                            refresh={() => undefined}
                        />
                    </RoutePathProvider>
                </ModalProvider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

/** The single-line name field, whichever locale tab is shown */
const nameField = () =>
    screen.getByLabelText('Name', {selector: 'input'}) as HTMLInputElement;

describe('RenditionDefinitionsTab', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getRenditionDefinitions.mockResolvedValue({
            total: 1,
            items: [definition],
        });
        api.getRenditionDefinition.mockResolvedValue(definition);
        api.getRenditionPolicies.mockResolvedValue({total: 0, items: []});
        api.getRenditionBuildReference.mockResolvedValue({references: []});
        api.putRenditionDefinition.mockImplementation(
            async (_id: string, data: Partial<RenditionDefinition>) => ({
                ...definition,
                ...data,
            })
        );
    });

    it('shows the translations of the name, one tab per locale', async () => {
        renderTab(['fr', 'de']);

        await waitFor(() => expect(nameField().value).toBe('preview'));
        expect(screen.getByRole('tab', {name: /fr/})).toBeTruthy();
        expect(screen.getByRole('tab', {name: /de/})).toBeTruthy();

        fireEvent.mouseDown(screen.getByRole('tab', {name: /fr/}), {
            button: 0,
        });

        await waitFor(() => expect(nameField().value).toBe('Aperçu'));
        expect(nameField().placeholder).toBe('preview');
    });

    it('sends the translations of the name', async () => {
        renderTab(['fr', 'de']);

        await waitFor(() => expect(nameField().value).toBe('preview'));
        fireEvent.mouseDown(screen.getByRole('tab', {name: /de/}), {
            button: 0,
        });
        await waitFor(() => expect(nameField().value).toBe(''));
        fireEvent.change(nameField(), {target: {value: 'Vorschau'}});

        fireEvent.click(screen.getByRole('button', {name: /Save/}));

        await waitFor(() =>
            expect(api.putRenditionDefinition).toHaveBeenCalledTimes(1)
        );
        const [id, data] = api.putRenditionDefinition.mock.calls[0];
        expect(id).toBe('r1');
        expect(data).toMatchObject({
            name: 'preview',
            translations: {name: {fr: 'Aperçu', de: 'Vorschau'}},
        });
    });

    it('tells that the main rendition is the web display of the viewer', async () => {
        renderTab();

        await waitFor(() => expect(nameField().value).toBe('preview'));
        expect(screen.getByText('Use as main (web display)')).toBeTruthy();
        expect(
            screen.getByText(/Displayed by the asset viewer: must be readable/)
        ).toBeTruthy();
    });

    it('edits the name alone without locale', async () => {
        renderTab();

        await waitFor(() => expect(nameField().value).toBe('preview'));
        expect(screen.queryAllByRole('tab')).toHaveLength(0);

        fireEvent.change(nameField(), {target: {value: 'large'}});
        fireEvent.click(screen.getByRole('button', {name: /Save/}));

        await waitFor(() =>
            expect(api.putRenditionDefinition).toHaveBeenCalledTimes(1)
        );
        expect(api.putRenditionDefinition.mock.calls[0][1]).toMatchObject({
            name: 'large',
            translations: {name: {fr: 'Aperçu'}},
        });
    });
});
