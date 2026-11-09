'use client';

import {createContext, useContext, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    EyeIcon,
    EyeOffIcon,
    LockIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {FieldError, FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {Tabs, TabsList, TabsTrigger} from '@alchemy/next-ui/ui/misc';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {TagsInput} from '@/components/form/TagsInput';
import {cn} from '@alchemy/next-ui/utils/cn';
import {
    ConfigErrors,
    ConfigListSchema,
    ConfigObjectSchema,
    ConfigSchema,
    ConfigValues,
    fromYaml,
    globalErrorKey,
    labelOf,
    toYaml,
    variantOf,
} from './schema';
import {AttributePicker} from './pickers/AttributePicker';
import {RenditionPicker} from './pickers/RenditionPicker';
import {CollectionPicker} from './pickers/CollectionPicker';
import {OAuthClientPicker} from './pickers/OAuthClientPicker';
import {SecretReferencePicker} from './pickers/SecretReferencePicker';
import {CreateSecretDialog} from './pickers/CreateSecretDialog';
import type {WorkspaceSecret} from '@/types/api';
import {
    parseReference,
    suggestSecretName,
    toReference,
} from '@/features/secrets/secretRef';
import {useSecretNames} from '@/features/secrets/useSecretNames';

type FormProps = {
    schema: ConfigObjectSchema;
    value: ConfigValues;
    onChange: (value: ConfigValues) => void;
    errors?: ConfigErrors;
    /** Omitted for the instance-wide integrations: no resource to pick from */
    workspaceId?: string;
    /** Omitted for an integration not saved yet */
    integrationId?: string;
    /** Title of the integration, to name what is created for it */
    integrationTitle?: string;
};

/** The integration being configured, for the fields bound to it */
const IntegrationContext = createContext<{
    integrationId?: string;
    integrationTitle?: string;
}>({});

const noErrors: ConfigErrors = {};
const noValue = '__none__';

/**
 * Form generated from the configuration schema of an integration.
 */
export function ConfigForm({
    schema,
    value,
    onChange,
    errors = noErrors,
    workspaceId,
    integrationId,
    integrationTitle,
}: FormProps) {
    const properties = schema.properties ?? {};
    // Bound to no field of the form: shown on top
    const global = Object.entries(errors)
        .filter(
            ([path]) =>
                path === globalErrorKey || !(path.split('.')[0] in properties)
        )
        .map(([, message]) => message)
        .join('\n');

    return (
        <div data-testid="integration-config-form">
            {global ? (
                <FieldError>
                    <span className="whitespace-pre-line">{global}</span>
                </FieldError>
            ) : null}
            <IntegrationContext.Provider
                value={{integrationId, integrationTitle}}
            >
                <ObjectFields
                    schema={schema}
                    path=""
                    value={value}
                    onChange={onChange}
                    errors={errors}
                    workspaceId={workspaceId}
                />
            </IntegrationContext.Provider>
        </div>
    );
}

type FieldProps<V = unknown> = {
    schema: ConfigSchema;
    name: string;
    /** Dotted path from the root, lists indexed (`labels.attributes.0.name`) */
    path: string;
    value: V;
    onChange: (value: V) => void;
    errors: ConfigErrors;
    workspaceId?: string;
};

function join(path: string, key: string | number): string {
    return path === '' ? String(key) : `${path}.${key}`;
}

function ObjectFields({
    schema,
    path,
    value,
    onChange,
    errors,
    workspaceId,
    except,
}: {
    schema: ConfigObjectSchema;
    path: string;
    value: ConfigValues | undefined;
    onChange: (value: ConfigValues) => void;
    errors: ConfigErrors;
    workspaceId?: string;
    except?: string[];
}) {
    const values = value ?? {};

    return (
        <>
            {Object.entries(schema.properties ?? {})
                .filter(([key]) => !except?.includes(key))
                .map(([key, property]) => (
                    <ConfigField
                        key={key}
                        schema={property}
                        name={key}
                        path={join(path, key)}
                        value={values[key]}
                        onChange={v => {
                            const next = {...values};
                            if (v === undefined) {
                                delete next[key];
                            } else {
                                next[key] = v;
                            }
                            onChange(next);
                        }}
                        errors={errors}
                        workspaceId={workspaceId}
                    />
                ))}
        </>
    );
}

export function ConfigField(props: FieldProps) {
    const {schema} = props;
    switch (schema.type) {
        case 'string':
            return <StringField {...props} schema={schema} />;
        case 'integer':
        case 'float':
            return <NumberField {...props} schema={schema} />;
        case 'boolean':
            return <BooleanField {...props} schema={schema} />;
        case 'enum':
            return <EnumField {...props} schema={schema} />;
        case 'any':
            return <YamlField {...props} />;
        case 'object':
            return <ObjectField {...props} schema={schema} />;
        case 'list':
            return <ListField {...props} schema={schema} />;
        default:
            return <YamlField {...props} />;
    }
}

function useFieldChrome({schema, name, path, errors}: FieldProps) {
    return {
        id: `config-${path}`,
        testId: `config-field-${path}`,
        label: labelOf(schema, name),
        error: errors[path],
        help: schema.info,
        placeholder:
            schema.example !== undefined ? String(schema.example) : undefined,
    };
}

function StringField(props: FieldProps & {schema: ConfigSchema}) {
    const {schema, value, onChange, workspaceId} = props;
    const {id, testId, label, error, help, placeholder} = useFieldChrome(props);
    const {integrationId, integrationTitle} = useContext(IntegrationContext);
    const text = typeof value === 'string' ? value : (value ?? '');
    const set = (v: string | undefined) => onChange(v === '' ? undefined : v);

    let control;
    switch (schema.widget) {
        case 'attribute':
            control = (
                <AttributePicker
                    id={id}
                    workspaceId={workspaceId}
                    constraints={schema.widgetOptions ?? {}}
                    value={text === '' ? undefined : String(text)}
                    onChange={set}
                />
            );
            break;
        case 'rendition':
            control = (
                <RenditionPicker
                    id={id}
                    workspaceId={workspaceId}
                    value={text === '' ? undefined : String(text)}
                    onChange={set}
                />
            );
            break;
        case 'collection':
            control = (
                <CollectionPicker
                    id={id}
                    workspaceId={workspaceId}
                    value={text === '' ? undefined : String(text)}
                    onChange={set}
                />
            );
            break;
        case 'oauth_client':
            control = (
                <OAuthClientPicker
                    value={text === '' ? undefined : String(text)}
                    onChange={set}
                    integrationId={integrationId}
                    integrationTitle={integrationTitle}
                >
                    <Input
                        id={id}
                        type="text"
                        value={String(text)}
                        onChange={e => set(e.target.value)}
                        placeholder={placeholder}
                        required={schema.required}
                        aria-invalid={!!error}
                        autoComplete="off"
                        className={cn(
                            parseReference(text) !== undefined && 'font-mono'
                        )}
                    />
                </OAuthClientPicker>
            );
            break;
        default:
            return <TextField {...props} />;
    }

    return (
        <FormRow
            label={label}
            htmlFor={id}
            error={error}
            help={help}
            data-testid={testId}
        >
            {control}
        </FormRow>
    );
}

type TextMode = 'value' | 'reference';

/**
 * A plain text value, or a reference to a secret or variable (`${NAME}`)
 * resolved by the API. A value mixing text and references
 * (`https://${HOST}/x`) is edited as a value.
 */
function TextField(props: FieldProps & {schema: ConfigSchema}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {schema, name, path, value, onChange, workspaceId} = props;
    const {id, testId, label, error, help, placeholder} = useFieldChrome(props);
    const text =
        typeof value === 'string'
            ? value
            : value === undefined || value === null
              ? ''
              : String(value);
    const [mode, setMode] = useState<TextMode>(() =>
        parseReference(text) !== undefined ? 'reference' : 'value'
    );
    const [shown, setShown] = useState(false);
    const set = (v: string | undefined) => onChange(v === '' ? undefined : v);
    // A secret typed in clear can become a secret of the workspace, once its
    // listing tells the user is allowed to manage them
    const offerSecret = !!schema.secret && mode === 'value';
    const {secrets} = useSecretNames({workspaceId}, offerSecret);

    const switchMode = (next: TextMode) => {
        if (next !== mode) {
            setMode(next);
            set(undefined);
        }
    };

    const saveAsSecret = async () => {
        const created: WorkspaceSecret | undefined = await openModal(
            CreateSecretDialog,
            {workspaceId, name: suggestSecretName(name), value: text}
        );
        if (created) {
            setMode('reference');
            onChange(toReference(created.name));
        }
    };

    const control =
        mode === 'reference' ? (
            <SecretReferencePicker
                id={id}
                name={parseReference(text) ?? ''}
                onChange={n => set(n === '' ? undefined : toReference(n))}
                workspaceId={workspaceId}
                required={schema.required}
                invalid={!!error}
            />
        ) : (
            <div className="flex items-center gap-1">
                <Input
                    id={id}
                    type={
                        schema.secret && !shown
                            ? 'password'
                            : schema.widget === 'email'
                              ? 'email'
                              : 'text'
                    }
                    value={text}
                    onChange={e => set(e.target.value)}
                    placeholder={placeholder}
                    required={schema.required}
                    aria-invalid={!!error}
                    autoComplete="off"
                />
                {schema.secret ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={
                            shown
                                ? t('integration.config.hide_secret', 'Hide')
                                : t('integration.config.show_secret', 'Show')
                        }
                        aria-pressed={shown}
                        onClick={() => setShown(s => !s)}
                    >
                        {shown ? <EyeOffIcon /> : <EyeIcon />}
                    </Button>
                ) : null}
                {offerSecret && secrets ? (
                    <Button
                        type="button"
                        variant="outline"
                        data-testid={`config-save-secret-${path}`}
                        disabled={text === ''}
                        onClick={() => void saveAsSecret()}
                    >
                        <LockIcon />
                        {t(
                            'integration.config.save_as_secret',
                            'Save as secret'
                        )}
                    </Button>
                ) : null}
            </div>
        );

    return (
        <FormRow
            label={label}
            htmlFor={id}
            error={error}
            help={help}
            data-testid={testId}
        >
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">{control}</div>
                <Tabs
                    value={mode}
                    onValueChange={v => switchMode(v as TextMode)}
                >
                    <TabsList
                        aria-label={t(
                            'integration.config.mode_label',
                            'Value or reference to a secret'
                        )}
                    >
                        <TabsTrigger
                            value="value"
                            data-testid={`config-mode-value-${path}`}
                        >
                            {t('integration.config.mode_value', 'Value')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="reference"
                            data-testid={`config-mode-reference-${path}`}
                            title={t(
                                'integration.config.mode_reference_help',
                                'Reference to a secret or variable, resolved when the integration runs'
                            )}
                        >
                            {t(
                                'integration.config.mode_reference',
                                'Reference'
                            )}
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>
        </FormRow>
    );
}

function NumberField(
    props: FieldProps & {
        schema: Extract<ConfigSchema, {type: 'integer' | 'float'}>;
    }
) {
    const {schema, value, onChange} = props;
    const {id, testId, label, error, help, placeholder} = useFieldChrome(props);

    return (
        <FormRow
            label={label}
            htmlFor={id}
            error={error}
            help={help}
            data-testid={testId}
        >
            <Input
                id={id}
                type="number"
                min={schema.min}
                max={schema.max}
                step={schema.type === 'integer' ? 1 : 'any'}
                value={typeof value === 'number' ? value : ''}
                onChange={e => {
                    const raw = e.target.value;
                    onChange(raw === '' ? undefined : Number(raw));
                }}
                placeholder={placeholder}
                required={schema.required}
                aria-invalid={!!error}
            />
        </FormRow>
    );
}

function BooleanField(props: FieldProps & {schema: ConfigSchema}) {
    const {value, onChange} = props;
    const {id, testId, label, error, help} = useFieldChrome(props);

    return (
        <FormRow error={error} data-testid={testId}>
            <LabeledControl label={label} description={help}>
                <Switch id={id} checked={!!value} onCheckedChange={onChange} />
            </LabeledControl>
        </FormRow>
    );
}

function EnumField(
    props: FieldProps & {schema: Extract<ConfigSchema, {type: 'enum'}>}
) {
    const {schema, value, onChange} = props;
    const {id, testId, label, error, help} = useFieldChrome(props);
    const options = schema.values.map(v => ({
        value: String(v),
        label: String(v),
    }));
    if (!schema.required) {
        options.unshift({value: noValue, label: '—'});
    }

    return (
        <FormRow
            label={label}
            htmlFor={id}
            error={error}
            help={help}
            data-testid={testId}
        >
            <SimpleSelect
                id={id}
                value={
                    value === undefined || value === null
                        ? schema.required
                            ? undefined
                            : noValue
                        : String(value)
                }
                onValueChange={v =>
                    onChange(
                        v === noValue
                            ? undefined
                            : schema.values.find(x => String(x) === v)
                    )
                }
                options={options}
            />
        </FormRow>
    );
}

/**
 * YAML editor of a single node, for what the schema does not describe.
 * The text is kept as typed while it does not parse.
 */
function YamlField(props: FieldProps) {
    const {t} = useTranslation();
    const {value, onChange} = props;
    const {id, testId, label, error, help} = useFieldChrome(props);
    const [text, setText] = useState(() => yamlOf(value));
    const [parseError, setParseError] = useState<string>();
    const emitted = useRef(value);

    useEffect(() => {
        if (value !== emitted.current) {
            emitted.current = value;
            setText(yamlOf(value));
            setParseError(undefined);
        }
    }, [value]);

    const edit = (next: string) => {
        setText(next);
        try {
            const parsed = parseYamlValue(next);
            emitted.current = parsed;
            setParseError(undefined);
            onChange(parsed);
        } catch (e) {
            setParseError(
                t(
                    'integration.config.yaml_invalid',
                    'Invalid YAML: {{error}}',
                    {
                        error: (e as Error).message,
                    }
                )
            );
        }
    };

    return (
        <FormRow
            label={label}
            htmlFor={id}
            error={parseError ?? error}
            help={help}
            data-testid={testId}
        >
            <CodeEditor
                id={id}
                mode="yaml"
                minLines={3}
                value={text}
                onChange={edit}
                aria-invalid={!!(parseError ?? error)}
            />
        </FormRow>
    );
}

function yamlOf(value: unknown): string {
    if (value === undefined || value === null) {
        return '';
    }
    if (typeof value === 'object' && !Array.isArray(value)) {
        return toYaml(value as ConfigValues);
    }

    return toYaml({value} as ConfigValues).replace(/^value:\s*/, '');
}

function parseYamlValue(text: string): unknown {
    if (text.trim() === '') {
        return undefined;
    }
    try {
        return fromYaml(text);
    } catch (e) {
        // A scalar or a sequence, wrapped in a mapping to be parsed the same way
        if (/must be a YAML mapping/.test((e as Error).message)) {
            return fromYaml(`value:\n${indent(text)}`).value;
        }
        throw e;
    }
}

function indent(text: string): string {
    return text
        .split('\n')
        .map(l => `  ${l}`)
        .join('\n');
}

function ObjectField(props: FieldProps & {schema: ConfigObjectSchema}) {
    const {schema, value, onChange, path, errors, workspaceId} = props;
    const {testId, label, error, help} = useFieldChrome(props);
    const values = (value ?? {}) as ConfigValues;
    const toggle = schema.toggle;
    const enabled = toggle ? !!values[toggle] : true;

    return (
        <fieldset
            data-testid={testId}
            className="mb-4 rounded-lg border p-3"
            aria-invalid={!!error}
        >
            <legend className="px-1 text-sm font-medium">
                {toggle ? (
                    <LabeledControl label={label}>
                        <Switch
                            data-testid={`config-toggle-${path}`}
                            checked={enabled}
                            onCheckedChange={v =>
                                onChange({...values, [toggle]: v})
                            }
                        />
                    </LabeledControl>
                ) : (
                    label
                )}
            </legend>
            {help ? (
                <p className="mb-3 text-xs text-muted-foreground">{help}</p>
            ) : null}
            <FieldError>{error}</FieldError>
            {enabled ? (
                <VariantFields
                    schema={schema}
                    path={path}
                    value={values}
                    onChange={onChange}
                    errors={errors}
                    workspaceId={workspaceId}
                    except={toggle ? [toggle] : undefined}
                />
            ) : null}
        </fieldset>
    );
}

/**
 * The fields of an object; a polymorphic one starts with the choice of its
 * variant.
 */
function VariantFields({
    schema,
    path,
    value,
    onChange,
    errors,
    workspaceId,
    except,
}: {
    schema: ConfigObjectSchema;
    path: string;
    value: ConfigValues;
    onChange: (value: ConfigValues) => void;
    errors: ConfigErrors;
    workspaceId?: string;
    except?: string[];
}) {
    const {t} = useTranslation();
    if (!schema.variants || !schema.discriminator) {
        return (
            <ObjectFields
                schema={schema}
                path={path}
                value={value}
                onChange={onChange}
                errors={errors}
                workspaceId={workspaceId}
                except={except}
            />
        );
    }
    const discriminator = schema.discriminator;
    const variant = variantOf(schema, value);
    const current = value[discriminator];
    const selectPath = join(path, discriminator);

    return (
        <>
            <FormRow
                label={t('integration.config.variant', 'Type')}
                htmlFor={`config-${selectPath}`}
                error={errors[selectPath]}
                data-testid={`config-field-${selectPath}`}
            >
                <SimpleSelect
                    id={`config-${selectPath}`}
                    value={typeof current === 'string' ? current : undefined}
                    onValueChange={v => onChange({[discriminator]: v})}
                    options={Object.keys(schema.variants).map(name => ({
                        value: name,
                        label: schema.variants![name].label ?? name,
                    }))}
                />
            </FormRow>
            {variant ? (
                <ObjectFields
                    schema={variant}
                    path={path}
                    value={value}
                    onChange={onChange}
                    errors={errors}
                    workspaceId={workspaceId}
                    except={[...(except ?? []), discriminator]}
                />
            ) : null}
        </>
    );
}

function ListField(props: FieldProps & {schema: ConfigListSchema}) {
    const {t} = useTranslation();
    const {schema, value, onChange, path, errors, workspaceId} = props;
    const {id, testId, label, error, help} = useFieldChrome(props);
    const items = Array.isArray(value) ? value : [];
    const itemSchema = schema.items;
    const rowProps = {
        label,
        'htmlFor': id,
        error,
        help,
        'data-testid': testId,
    };

    if (
        itemSchema.widget === 'attribute' ||
        itemSchema.widget === 'rendition'
    ) {
        const Picker =
            itemSchema.widget === 'attribute'
                ? AttributePicker
                : RenditionPicker;

        return (
            <FormRow {...rowProps}>
                <Picker
                    id={id}
                    multiple
                    workspaceId={workspaceId}
                    constraints={itemSchema.widgetOptions ?? {}}
                    value={items.map(String)}
                    onChange={onChange}
                />
            </FormRow>
        );
    }

    if (itemSchema.type === 'enum') {
        return (
            <FormRow {...rowProps}>
                <div className="flex flex-wrap gap-x-4 gap-y-2" id={id}>
                    {itemSchema.values.map(v => (
                        <LabeledControl key={String(v)} label={String(v)}>
                            <Checkbox
                                checked={items.includes(v)}
                                onCheckedChange={checked =>
                                    onChange(
                                        checked === true
                                            ? [...items, v]
                                            : items.filter(x => x !== v)
                                    )
                                }
                            />
                        </LabeledControl>
                    ))}
                </div>
            </FormRow>
        );
    }

    if (
        itemSchema.type === 'string' ||
        itemSchema.type === 'integer' ||
        itemSchema.type === 'float'
    ) {
        const numeric = itemSchema.type !== 'string';

        return (
            <FormRow {...rowProps}>
                <TagsInput
                    id={id}
                    value={items.map(String)}
                    onChange={v =>
                        onChange(
                            numeric
                                ? v.map(Number).filter(n => !Number.isNaN(n))
                                : v
                        )
                    }
                    placeholder={
                        itemSchema.example !== undefined
                            ? String(itemSchema.example)
                            : undefined
                    }
                    aria-invalid={!!error}
                />
            </FormRow>
        );
    }

    if (itemSchema.type === 'object') {
        const setItem = (index: number, v: ConfigValues) =>
            onChange(items.map((item, i) => (i === index ? v : item)));

        return (
            <FormRow {...rowProps}>
                <div className="space-y-3" id={id}>
                    {items.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            {t('integration.config.list_empty', 'Nothing yet')}
                        </p>
                    ) : null}
                    {items.map((item, index) => (
                        <div
                            key={index}
                            className="rounded-lg border p-3"
                            data-testid={`config-list-item-${join(path, index)}`}
                        >
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-xs font-medium text-muted-foreground">
                                    #{index + 1}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    aria-label={t(
                                        'integration.config.list_remove',
                                        'Remove'
                                    )}
                                    data-testid={`config-list-remove-${join(path, index)}`}
                                    onClick={() =>
                                        onChange(
                                            items.filter((_, i) => i !== index)
                                        )
                                    }
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                            <VariantFields
                                schema={itemSchema}
                                path={join(path, index)}
                                value={(item ?? {}) as ConfigValues}
                                onChange={v => setItem(index, v)}
                                errors={errors}
                                workspaceId={workspaceId}
                            />
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-testid={`config-list-add-${path}`}
                        onClick={() => onChange([...items, {}])}
                    >
                        <PlusIcon /> {t('integration.config.list_add', 'Add')}
                    </Button>
                </div>
            </FormRow>
        );
    }

    // Lists of free values, or of lists: YAML of the whole list
    return <YamlField {...props} />;
}
