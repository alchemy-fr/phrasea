import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, within} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {useState} from 'react';
import {createI18n} from '@/i18n';
import {ConfigForm} from './ConfigForm';
import type {ConfigObjectSchema, ConfigValues} from './schema';

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
                <Harness {...props} onChange={onChange} />
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
