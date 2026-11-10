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
import {useState} from 'react';
import {createI18n} from '@/i18n';
import {ApiError} from '@alchemy/next-ui/utils/errors';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {ConfigForm} from './ConfigForm';
import type {ConfigObjectSchema, ConfigValues} from './schema';

const {auth, oauthApi, secretsApi} = vi.hoisted(() => ({
    auth: {canViewOAuthClients: false, canManageOAuthClients: false},
    oauthApi: {getOAuthClients: vi.fn(), createOAuthClient: vi.fn()},
    secretsApi: {
        secretsQueryKey: 'workspace-secrets',
        envsQueryKey: 'workspace-envs',
        getWorkspaceSecrets: vi.fn(),
        getWorkspaceEnvs: vi.fn(),
        postWorkspaceSecret: vi.fn(),
        putWorkspaceSecret: vi.fn(),
        deleteWorkspaceSecret: vi.fn(),
        postWorkspaceEnv: vi.fn(),
        putWorkspaceEnv: vi.fn(),
        deleteWorkspaceEnv: vi.fn(),
    },
}));
vi.mock('@/lib/auth/AuthProvider', () => ({useAuth: () => auth}));
vi.mock('@/lib/api/oauthClients', () => oauthApi);
vi.mock('@/lib/api/secrets', () => secretsApi);

function page<T>(items: T[]) {
    return {items, total: items.length};
}

beforeEach(() => {
    secretsApi.getWorkspaceSecrets.mockResolvedValue(page([]));
    secretsApi.getWorkspaceEnvs.mockResolvedValue(page([]));
});

// Used by the Radix popover and cmdk, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => {};

// Ace is not for jsdom: a plain textarea stands in for the YAML editor
vi.mock('@/components/form/code/CodeEditor', () => ({
    CodeEditor: ({
        id,
        value,
        onChange,
    }: {
        id?: string;
        value: string;
        onChange: (v: string) => void;
    }) => (
        <textarea
            id={id}
            data-testid="code-editor"
            value={value}
            onChange={e => onChange(e.target.value)}
        />
    ),
}));

const schema: ConfigObjectSchema = {
    type: 'object',
    properties: {
        apiKey: {type: 'string', secret: true, label: 'API key'},
        baseUrl: {type: 'string', label: 'Base URL'},
        maxImageSize: {
            type: 'integer',
            min: 256,
            max: 4096,
            default: 2048,
            info: 'Longest side',
        },
        processIncoming: {type: 'boolean', default: false},
        method: {type: 'enum', values: ['api', 'enqueue'], default: 'api'},
        labels: {
            type: 'object',
            toggle: 'enabled',
            properties: {
                enabled: {type: 'boolean', default: false},
                threshold: {type: 'float', default: 0.5},
            },
        },
        emails: {type: 'list', items: {type: 'string', widget: 'email'}},
        actions: {
            type: 'list',
            items: {type: 'enum', values: ['reject', 'quarantine']},
        },
        attributes: {
            type: 'list',
            items: {
                type: 'object',
                properties: {
                    name: {type: 'string', required: true},
                    threshold: {type: 'float'},
                },
            },
        },
        analyzers: {
            type: 'list',
            items: {
                type: 'object',
                discriminator: 'name',
                variants: {
                    checksum: {
                        type: 'object',
                        label: 'Checksum',
                        properties: {
                            name: {type: 'string', required: true},
                            algorithm: {type: 'string', default: 'sha256'},
                        },
                    },
                    size: {
                        type: 'object',
                        properties: {
                            name: {type: 'string', required: true},
                            max: {type: 'integer'},
                        },
                    },
                },
            },
        },
        options: {type: 'any'},
    },
};

function Harness({
    initial = {},
    errors,
    onChange,
}: {
    initial?: ConfigValues;
    errors?: Record<string, string>;
    onChange: (v: ConfigValues) => void;
}) {
    const [value, setValue] = useState<ConfigValues>(initial);

    return (
        <ConfigForm
            schema={schema}
            value={value}
            onChange={v => {
                setValue(v);
                onChange(v);
            }}
            errors={errors}
            workspaceId="ws"
        />
    );
}

function renderForm(props: {
    initial?: ConfigValues;
    errors?: Record<string, string>;
}) {
    const onChange = vi.fn();
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <QueryClientProvider client={new QueryClient()}>
                <ModalProvider>
                    <Harness {...props} onChange={onChange} />
                </ModalProvider>
            </QueryClientProvider>
        </I18nextProvider>
    );
    const last = () => onChange.mock.lastCall?.[0] as ConfigValues;

    return {onChange, last};
}

describe('ConfigForm', () => {
    it('renders one field per property, labelled from the schema', () => {
        renderForm({});

        expect(screen.getByLabelText('API key')).toBeTruthy();
        expect(screen.getByLabelText('Max image size')).toBeTruthy();
        expect(screen.getByText('Longest side')).toBeTruthy();
        expect(screen.getByTestId('integration-config-form')).toBeTruthy();
    });

    it('masks a secret until it is shown', () => {
        renderForm({initial: {apiKey: 'xyz'}});
        const input = screen.getByLabelText('API key') as HTMLInputElement;

        expect(input.type).toBe('password');
        fireEvent.click(screen.getByRole('button', {name: 'Show'}));
        expect(input.type).toBe('text');
    });

    it('reads an integer, and clears it when emptied', () => {
        const {last} = renderForm({initial: {maxImageSize: 2048}});
        const input = screen.getByLabelText(
            'Max image size'
        ) as HTMLInputElement;

        expect(input.min).toBe('256');
        fireEvent.change(input, {target: {value: '1024'}});
        expect(last()).toEqual({maxImageSize: 1024});

        fireEvent.change(input, {target: {value: ''}});
        expect(last()).toEqual({});
    });

    it('switches a boolean', () => {
        const {last} = renderForm({});

        fireEvent.click(screen.getByLabelText('Process incoming'));
        expect(last()).toEqual({processIncoming: true});
    });

    it('hides the fields of a toggled object until it is enabled', () => {
        const {last} = renderForm({initial: {labels: {enabled: false}}});

        expect(screen.queryByLabelText('Threshold')).toBeNull();
        fireEvent.click(screen.getByTestId('config-toggle-labels'));
        expect(last()).toEqual({labels: {enabled: true}});
        expect(screen.getByLabelText('Threshold')).toBeTruthy();
    });

    it('collects a list of scalars as chips', () => {
        const {last} = renderForm({initial: {emails: ['a@b.c']}});
        const input = screen.getByLabelText('Emails') as HTMLInputElement;

        fireEvent.change(input, {target: {value: 'd@e.f'}});
        fireEvent.keyDown(input, {key: 'Enter'});
        expect(last()).toEqual({emails: ['a@b.c', 'd@e.f']});

        fireEvent.keyDown(input, {key: 'Backspace'});
        expect(last()).toEqual({emails: ['a@b.c']});

        fireEvent.paste(input, {
            clipboardData: {getData: () => 'g@h.i, j@k.l'},
        });
        expect(last()).toEqual({emails: ['a@b.c', 'g@h.i', 'j@k.l']});
    });

    it('offers a list of enums as checkboxes', () => {
        const {last} = renderForm({initial: {actions: ['reject']}});

        fireEvent.click(screen.getByLabelText('quarantine'));
        expect(last()).toEqual({actions: ['reject', 'quarantine']});
        fireEvent.click(screen.getByLabelText('reject'));
        expect(last()).toEqual({actions: ['quarantine']});
    });

    it('adds and removes the objects of a list', () => {
        const {last} = renderForm({});

        fireEvent.click(screen.getByTestId('config-list-add-attributes'));
        expect(last()).toEqual({attributes: [{}]});

        const card = screen.getByTestId('config-list-item-attributes.0');
        fireEvent.change(within(card).getByLabelText('Name'), {
            target: {value: 'tags'},
        });
        expect(last()).toEqual({attributes: [{name: 'tags'}]});

        fireEvent.click(screen.getByTestId('config-list-remove-attributes.0'));
        expect(last()).toEqual({attributes: []});
    });

    it('shows the fields of the chosen variant of a polymorphic object', () => {
        renderForm({
            initial: {analyzers: [{name: 'checksum'}, {name: 'size'}]},
        });

        const first = screen.getByTestId('config-list-item-analyzers.0');
        expect(within(first).getByLabelText('Algorithm')).toBeTruthy();
        expect(within(first).queryByLabelText('Max')).toBeNull();
        // The discriminator is the variant select, not a text field
        expect(within(first).queryByLabelText('Name')).toBeNull();

        const second = screen.getByTestId('config-list-item-analyzers.1');
        expect(within(second).getByLabelText('Max')).toBeTruthy();
    });

    it('edits a free node as YAML, keeping the text while it is invalid', () => {
        const {last, onChange} = renderForm({initial: {options: {a: 1}}});
        const editor = screen.getByTestId('code-editor') as HTMLTextAreaElement;

        expect(editor.value).toBe('a: 1\n');
        fireEvent.change(editor, {target: {value: 'a: [\n'}});
        expect(onChange).not.toHaveBeenCalled();
        expect(
            within(screen.getByTestId('config-field-options')).getByText(
                /Invalid YAML/
            )
        ).toBeTruthy();

        fireEvent.change(editor, {target: {value: 'a: 2\nb: x\n'}});
        expect(last()).toEqual({options: {a: 2, b: 'x'}});
    });

    it('shows the errors under their field, the others on top', () => {
        renderForm({
            errors: {
                'maxImageSize': 'Too small',
                'labels.threshold': 'Too high',
                '_global': 'Missing "attribute"',
                'unknown.path': 'Unexpected',
            },
            initial: {labels: {enabled: true}},
        });

        expect(
            within(screen.getByTestId('config-field-maxImageSize')).getByText(
                'Too small'
            )
        ).toBeTruthy();
        expect(
            within(
                screen.getByTestId('config-field-labels.threshold')
            ).getByText('Too high')
        ).toBeTruthy();
        const form = screen.getByTestId('integration-config-form');
        expect(form.textContent).toContain('Missing "attribute"');
        expect(form.textContent).toContain('Unexpected');
    });
});

describe('ConfigForm OAuth client field', () => {
    const oauthSchema: ConfigObjectSchema = {
        type: 'object',
        properties: {
            clientId: {
                type: 'string',
                widget: 'oauth_client',
                required: true,
                label: 'Client ID',
            },
        },
    };

    function renderOAuthForm(initial: ConfigValues = {}) {
        const onChange = vi.fn();
        function OAuthHarness() {
            const [value, setValue] = useState<ConfigValues>(initial);

            return (
                <ConfigForm
                    schema={oauthSchema}
                    value={value}
                    onChange={v => {
                        setValue(v);
                        onChange(v);
                    }}
                    integrationId="int-1"
                    integrationTitle="Expose prod"
                />
            );
        }
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <QueryClientProvider client={new QueryClient()}>
                    <ModalProvider>
                        <OAuthHarness />
                    </ModalProvider>
                </QueryClientProvider>
            </I18nextProvider>
        );

        return () => onChange.mock.lastCall?.[0] as ConfigValues;
    }

    beforeEach(() => {
        vi.clearAllMocks();
        auth.canViewOAuthClients = false;
        auth.canManageOAuthClients = false;
        oauthApi.getOAuthClients.mockResolvedValue([
            {
                id: 'kc-1',
                clientId: 'expose-integration',
                name: 'Expose integration',
                redirectUris: ['https://api-databox.test/*'],
                redirectUriAllowed: true,
            },
            {
                id: 'kc-2',
                clientId: 'databox-app',
                redirectUris: ['https://databox.test/*'],
                redirectUriAllowed: false,
            },
        ]);
    });

    it('is a plain text field without the Keycloak roles', () => {
        const last = renderOAuthForm();

        expect(screen.queryByTestId('oauth-client-choose')).toBeNull();
        expect(screen.queryByTestId('oauth-client-create')).toBeNull();
        fireEvent.change(screen.getByLabelText('Client ID'), {
            target: {value: 'my-client'},
        });
        expect(last()).toEqual({clientId: 'my-client'});
    });

    it('offers to create a client with the manage role only', () => {
        auth.canManageOAuthClients = true;
        renderOAuthForm();

        expect(screen.queryByTestId('oauth-client-choose')).toBeNull();
        expect(screen.getByTestId('oauth-client-create')).toBeTruthy();
    });

    it('picks a client among the compatible ones', async () => {
        auth.canViewOAuthClients = true;
        auth.canManageOAuthClients = true;
        const last = renderOAuthForm({clientId: '${CLIENT_ID}'});

        // Still typed by hand (e.g. a variable)
        expect(
            (screen.getByLabelText('Client ID') as HTMLInputElement).value
        ).toBe('${CLIENT_ID}');
        expect(screen.getByTestId('oauth-client-create')).toBeTruthy();

        fireEvent.click(screen.getByTestId('oauth-client-choose'));
        await waitFor(() =>
            expect(screen.getByTestId('oauth-client-databox-app')).toBeTruthy()
        );
        expect(oauthApi.getOAuthClients).toHaveBeenCalledWith(
            {search: '', integrationId: 'int-1'},
            expect.anything()
        );
        // Only the clients not allowing the redirect URI are flagged
        expect(
            within(screen.getByTestId('oauth-client-databox-app')).getByText(
                'Redirect URI not allowed'
            )
        ).toBeTruthy();
        expect(
            within(
                screen.getByTestId('oauth-client-expose-integration')
            ).queryByText('Redirect URI not allowed')
        ).toBeNull();

        fireEvent.click(screen.getByTestId('oauth-client-expose-integration'));
        expect(last()).toEqual({clientId: 'expose-integration'});
    });

    it('fills the field with the created client', async () => {
        auth.canManageOAuthClients = true;
        oauthApi.createOAuthClient.mockImplementation(async d => ({
            id: 'kc-3',
            clientId: d.clientId,
            redirectUris: [],
            redirectUriAllowed: true,
        }));
        const last = renderOAuthForm();

        fireEvent.click(screen.getByTestId('oauth-client-create'));
        await waitFor(() =>
            expect(
                (
                    screen.getByLabelText('Client ID', {
                        selector: '#create-oauth-client-id',
                    }) as HTMLInputElement
                ).value
            ).toBe('expose-prod')
        );
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(last()).toEqual({clientId: 'expose-prod'}));
    });
});

describe('ConfigForm secret references', () => {
    const apiKeyInput = () =>
        screen.getByLabelText('API key') as HTMLInputElement;
    const modeTab = (field: string, mode: 'value' | 'reference') =>
        screen.getByTestId(`config-mode-${mode}-${field}`);
    const row = (field: string) =>
        within(screen.getByTestId(`config-field-${field}`));
    const status = (field: string) =>
        row(field).getByTestId('secret-ref-status').textContent;
    const groupHeadings = () =>
        Array.from(
            document.querySelectorAll('[cmdk-group-heading]'),
            h => h.textContent
        );

    beforeEach(() => {
        vi.clearAllMocks();
        secretsApi.getWorkspaceSecrets.mockResolvedValue(
            page([
                {
                    id: 's1',
                    name: 'BG_KEY',
                    createdAt: '',
                    updatedAt: '',
                },
            ])
        );
        secretsApi.getWorkspaceEnvs.mockResolvedValue(
            page([
                {
                    id: 'e1',
                    name: 'BASE_URL',
                    value: 'https://expose.test',
                    createdAt: '',
                    updatedAt: '',
                },
            ])
        );
    });

    it('opens a reference in reference mode, as a chip', async () => {
        renderForm({initial: {apiKey: '${BG_KEY}'}});

        expect(
            modeTab('apiKey', 'reference').getAttribute('aria-selected')
        ).toBe('true');
        expect(row('apiKey').getByTestId('secret-ref-chip').textContent).toBe(
            'BG_KEY'
        );
        await waitFor(() =>
            expect(status('apiKey')).toBe('Secret: its value is never shown')
        );
        expect(row('apiKey').getByTestId('secret-ref-chip').dataset.kind).toBe(
            'secret'
        );
        expect(secretsApi.getWorkspaceSecrets).toHaveBeenCalledWith(
            {workspaceId: 'ws'},
            expect.anything()
        );
    });

    it('opens a literal value in value mode, masked for a secret', () => {
        renderForm({initial: {apiKey: 'plain-key'}});

        expect(modeTab('apiKey', 'value').getAttribute('aria-selected')).toBe(
            'true'
        );
        expect(apiKeyInput().type).toBe('password');
        expect(apiKeyInput().value).toBe('plain-key');
    });

    it('keeps a value mixing text and references in value mode', () => {
        renderForm({initial: {apiKey: 'https://${HOST}/x'}});

        expect(modeTab('apiKey', 'value').getAttribute('aria-selected')).toBe(
            'true'
        );
        expect(apiKeyInput().value).toBe('https://${HOST}/x');
    });

    it('empties a value and opens the picker when switching to a reference', async () => {
        const {last} = renderForm({initial: {apiKey: 'plain-key'}});

        fireEvent.mouseDown(modeTab('apiKey', 'reference'));
        expect(last()).toEqual({});
        expect(
            modeTab('apiKey', 'reference').getAttribute('aria-selected')
        ).toBe('true');
        // A name missing from the listing can be typed
        fireEvent.change(await screen.findByTestId('secret-ref-search'), {
            target: {value: 'MY_KEY'},
        });
        fireEvent.click(screen.getByTestId('secret-ref-use'));
        expect(last()).toEqual({apiKey: '${MY_KEY}'});
    });

    it('keeps a reference as text when switching to a value, and back', async () => {
        const {onChange, last} = renderForm({
            initial: {apiKey: '${MY_VAR}'},
        });

        fireEvent.mouseDown(modeTab('apiKey', 'value'));
        expect(modeTab('apiKey', 'value').getAttribute('aria-selected')).toBe(
            'true'
        );
        expect(onChange).not.toHaveBeenCalled();
        // A reference is not masked, nor offered to be saved as a secret
        expect(apiKeyInput().type).toBe('text');
        expect(apiKeyInput().value).toBe('${MY_VAR}');
        expect(
            (
                (await screen.findByTestId(
                    'config-save-secret-apiKey'
                )) as HTMLButtonElement
            ).disabled
        ).toBe(true);

        fireEvent.change(apiKeyInput(), {target: {value: '${OTHER}'}});
        fireEvent.mouseDown(modeTab('apiKey', 'reference'));
        expect(last()).toEqual({apiKey: '${OTHER}'});
        expect(row('apiKey').getByTestId('secret-ref-chip').textContent).toBe(
            'OTHER'
        );
        expect(screen.queryByTestId('secret-ref-search')).toBeNull();
    });

    it('offers the secrets first to a secret field', async () => {
        const {last} = renderForm({initial: {apiKey: '$OLD_KEY'}});
        expect(row('apiKey').getByTestId('secret-ref-chip').textContent).toBe(
            'OLD_KEY'
        );

        fireEvent.click(row('apiKey').getByTestId('secret-ref-choose'));
        await screen.findByTestId('secret-ref-secret-BG_KEY');
        expect(groupHeadings()).toEqual(['Secrets', 'Variables']);
        // Only a secret is created for a secret field
        expect(screen.getByTestId('secret-ref-create')).toBeTruthy();
        expect(screen.queryByTestId('secret-ref-create-variable')).toBeNull();

        fireEvent.click(screen.getByTestId('secret-ref-secret-BG_KEY'));
        expect(last()).toEqual({apiKey: '${BG_KEY}'});
    });

    it('offers the variables first to a plain field, with their values', async () => {
        const {last} = renderForm({initial: {baseUrl: '${NONE}'}});

        fireEvent.click(row('baseUrl').getByTestId('secret-ref-choose'));
        const item = await screen.findByTestId('secret-ref-env-BASE_URL');
        expect(item.textContent).toContain('https://expose.test');
        expect(groupHeadings()).toEqual(['Variables', 'Secrets']);
        expect(
            screen
                .getAllByTestId(/^secret-ref-create/)
                .map(i => i.dataset.testid)
        ).toEqual(['secret-ref-create-variable', 'secret-ref-create']);

        fireEvent.click(item);
        expect(last()).toEqual({baseUrl: '${BASE_URL}'});
        await waitFor(() =>
            expect(status('baseUrl')).toBe('Variable= https://expose.test')
        );
    });

    it('warns of a variable referenced by a secret field', async () => {
        renderForm({initial: {apiKey: '${BASE_URL}'}});

        await waitFor(() =>
            expect(status('apiKey')).toBe(
                'Variable, stored in clear: prefer a secret for this field'
            )
        );
    });

    it('creates a variable from the picker, named after the key', async () => {
        secretsApi.postWorkspaceEnv.mockImplementation(async d => ({
            id: 'e2',
            name: d.name,
            value: d.value,
            workspace: '/workspaces/ws',
            createdAt: '',
            updatedAt: '',
        }));
        const {last} = renderForm({initial: {baseUrl: '${X}'}});

        fireEvent.click(row('baseUrl').getByTestId('secret-ref-choose'));
        fireEvent.click(
            await screen.findByTestId('secret-ref-create-variable')
        );
        const name = (await screen.findByLabelText('Name', {
            selector: '#create-variable-name',
        })) as HTMLInputElement;
        expect(name.value).toBe('BASE_URL');
        fireEvent.change(name, {target: {value: 'EXPOSE_URL'}});
        fireEvent.change(screen.getByLabelText('Value'), {
            target: {value: 'https://expose.test'},
        });
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(last()).toEqual({baseUrl: '${EXPOSE_URL}'}));
        expect(secretsApi.postWorkspaceEnv).toHaveBeenCalledWith({
            workspaceId: 'ws',
            name: 'EXPOSE_URL',
            value: 'https://expose.test',
        });
    });

    it('removes the reference with the chip', () => {
        const {last} = renderForm({initial: {apiKey: '${BG_KEY}'}});

        fireEvent.click(row('apiKey').getByTestId('secret-ref-clear'));
        expect(last()).toEqual({});
        expect(row('apiKey').queryByTestId('secret-ref-chip')).toBeNull();
    });

    it('tells a typed name is defined nowhere', async () => {
        renderForm({initial: {apiKey: '${MISSING}'}});

        await waitFor(() =>
            expect(status('apiKey')).toBe('Not defined in this workspace')
        );
        expect(row('apiKey').getByTestId('secret-ref-chip').dataset.kind).toBe(
            'undefined'
        );
    });

    it('saves a typed secret as a secret of the workspace', async () => {
        secretsApi.postWorkspaceSecret.mockImplementation(async d => ({
            id: 's2',
            name: d.name,
            workspace: '/workspaces/ws',
            createdAt: '',
            updatedAt: '',
        }));
        const {last} = renderForm({initial: {apiKey: 'plain-key'}});

        const save = await screen.findByTestId('config-save-secret-apiKey');
        fireEvent.click(save);
        const name = (await screen.findByLabelText('Name', {
            selector: '#create-secret-name',
        })) as HTMLInputElement;
        expect(name.value).toBe('API_KEY');
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(last()).toEqual({apiKey: '${API_KEY}'}));
        expect(secretsApi.postWorkspaceSecret).toHaveBeenCalledWith({
            workspaceId: 'ws',
            name: 'API_KEY',
            value: 'plain-key',
        });
        expect(
            modeTab('apiKey', 'reference').getAttribute('aria-selected')
        ).toBe('true');
    });

    it('keeps the free typing, quietly, when the listing is forbidden', async () => {
        const forbidden = new ApiError('Forbidden', 403, {});
        secretsApi.getWorkspaceSecrets.mockRejectedValue(forbidden);
        secretsApi.getWorkspaceEnvs.mockRejectedValue(forbidden);
        const {last} = renderForm({initial: {apiKey: '${BG_KEY}'}});

        await waitFor(() =>
            expect(status('apiKey')).toBe(
                'Reference to a workspace secret or variable'
            )
        );
        expect(screen.queryByText(/Forbidden/)).toBeNull();

        fireEvent.click(row('apiKey').getByTestId('secret-ref-choose'));
        fireEvent.change(await screen.findByTestId('secret-ref-search'), {
            target: {value: '${OTHER_KEY}'},
        });
        // Nothing to create without the right to list
        expect(screen.queryByTestId('secret-ref-create')).toBeNull();
        fireEvent.click(screen.getByTestId('secret-ref-use'));
        expect(last()).toEqual({apiKey: '${OTHER_KEY}'});

        // Nor any secret to create from a value
        fireEvent.mouseDown(modeTab('apiKey', 'value'));
        fireEvent.change(apiKeyInput(), {target: {value: 'plain'}});
        expect(apiKeyInput().value).toBe('plain');
        expect(screen.queryByTestId('config-save-secret-apiKey')).toBeNull();
    });
});
