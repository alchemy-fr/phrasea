'use client';

import {ReactNode, useId} from 'react';
import {useTranslation} from 'react-i18next';
import {PlusIcon, Trash2Icon} from 'lucide-react';
import {FieldHelp, Input, Label} from '@alchemy/next-ui/ui/input';
import {Switch, LabeledControl} from '@alchemy/next-ui/ui/controls';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert, Badge} from '@alchemy/next-ui/ui/misc';
import {
    CUSTOM_TYPES,
    emptyValue,
    hasOptions,
    widgetOf,
    widgetsFor,
} from '../definition';
import {Field} from '../render/FormRenderer';
import type {
    BuilderField,
    Choice,
    FieldConstraints,
    FieldOptionsLoader,
    FieldType,
    FormValue,
    InputField,
} from '../types';
import {LocalizedInput} from './LocalizedInput';

const TEXT_TYPES: FieldType[] = ['text', 'textarea'];

type Props = {
    field: BuilderField;
    onChange: (field: BuilderField) => void;
    locales: string[];
    loadOptions: FieldOptionsLoader;
    issue?: string;
    disabled?: boolean;
};

export function FieldInspector({
    field,
    onChange,
    locales,
    loadOptions,
    issue,
    disabled,
}: Props) {
    const {t} = useTranslation();
    const labelId = useId();

    if (field.source === 'layout') {
        return (
            <Panel>
                <Row label={t('builder.field.kind', 'Kind')}>
                    <SimpleSelect<LayoutKind>
                        value={field.kind}
                        disabled={disabled}
                        onValueChange={kind => onChange({...field, kind})}
                        options={[
                            {
                                value: 'section',
                                label: t('builder.layout.section', 'Section'),
                            },
                            {
                                value: 'text',
                                label: t('builder.layout.text', 'Text'),
                            },
                        ]}
                    />
                </Row>
                <Row
                    label={
                        field.kind === 'section'
                            ? t('builder.field.title', 'Title')
                            : t('builder.field.content', 'Content')
                    }
                    htmlFor={labelId}
                >
                    <LocalizedInput
                        id={labelId}
                        value={field.label}
                        locales={locales}
                        multiline={field.kind === 'text'}
                        disabled={disabled}
                        onChange={label => onChange({...field, label})}
                    />
                </Row>
            </Panel>
        );
    }

    return (
        <InputFieldInspector
            field={field}
            onChange={onChange}
            locales={locales}
            loadOptions={loadOptions}
            issue={issue}
            disabled={disabled}
        />
    );
}

type LayoutKind = 'section' | 'text';

function InputFieldInspector({
    field,
    onChange,
    locales,
    loadOptions,
    issue,
    disabled,
}: Omit<Props, 'field'> & {field: InputField}) {
    const {t} = useTranslation();
    const ids = {
        key: useId(),
        label: useId(),
        help: useId(),
        placeholder: useId(),
        type: useId(),
        widget: useId(),
    };
    const set = (patch: Partial<InputField>) => onChange({...field, ...patch});
    const custom = field.source === 'custom';
    const widgets = widgetsFor(field);
    const constraints = field.constraints ?? {};
    const setConstraint = (name: keyof FieldConstraints, value: string) => {
        const n = value.trim() === '' ? null : Number(value);
        set({
            constraints: {
                ...constraints,
                [name]: n === null || Number.isNaN(n) ? null : n,
            },
        });
    };

    return (
        <Panel>
            {issue ? (
                <Alert variant="warning" className="mb-3 text-sm">
                    {issue}
                </Alert>
            ) : null}
            <div className="mb-3 flex flex-wrap gap-1">
                <Badge variant="muted">
                    {t(`builder.source.${field.source}`, field.source)}
                </Badge>
                {field.attribute ? (
                    <>
                        <Badge variant="outline">
                            {t(
                                `builder.databox_type.${field.attribute.databoxType}`,
                                field.attribute.databoxType
                            )}
                        </Badge>
                        {field.attribute.entityListName ? (
                            <Badge variant="outline">
                                {field.attribute.entityListName}
                            </Badge>
                        ) : null}
                        {field.attribute.translatable ? (
                            <Badge variant="outline">
                                {t(
                                    'builder.field.translatable',
                                    'Translatable'
                                )}
                            </Badge>
                        ) : null}
                    </>
                ) : null}
            </div>

            <Row
                label={t('builder.field.key', 'Key')}
                htmlFor={ids.key}
                help={
                    custom
                        ? t(
                              'builder.field.key_help',
                              'The name of the value in the data sent to the target.'
                          )
                        : field.source === 'attribute'
                          ? t(
                                'builder.field.key_attribute_help',
                                'The Databox attribute filled by the field.'
                            )
                          : undefined
                }
            >
                <Input
                    id={ids.key}
                    value={field.key}
                    disabled={disabled || !custom}
                    className="font-mono"
                    onChange={e => set({key: e.target.value})}
                />
            </Row>

            {custom ? (
                <Row label={t('builder.field.type', 'Type')} htmlFor={ids.type}>
                    <SimpleSelect<FieldType>
                        id={ids.type}
                        value={field.type}
                        disabled={disabled}
                        onValueChange={type =>
                            set({
                                type,
                                widget: undefined,
                                defaultValue: undefined,
                                multiple:
                                    type === 'boolean'
                                        ? undefined
                                        : field.multiple,
                                choices:
                                    type === 'choice'
                                        ? (field.choices ?? [])
                                        : undefined,
                            })
                        }
                        options={CUSTOM_TYPES.map(type => ({
                            value: type,
                            label: t(`builder.type.${type}`, type),
                        }))}
                    />
                </Row>
            ) : null}

            <Row label={t('builder.field.label', 'Label')} htmlFor={ids.label}>
                <LocalizedInput
                    id={ids.label}
                    value={field.label}
                    locales={locales}
                    disabled={disabled}
                    placeholder={field.key}
                    onChange={label => set({label})}
                />
            </Row>
            <Row label={t('builder.field.help', 'Help')} htmlFor={ids.help}>
                <LocalizedInput
                    id={ids.help}
                    value={field.help}
                    locales={locales}
                    multiline
                    disabled={disabled}
                    onChange={help => set({help})}
                />
            </Row>
            {field.type !== 'boolean' ? (
                <Row
                    label={t('builder.field.placeholder', 'Placeholder')}
                    htmlFor={ids.placeholder}
                >
                    <LocalizedInput
                        id={ids.placeholder}
                        value={field.placeholder}
                        locales={locales}
                        disabled={disabled}
                        onChange={placeholder => set({placeholder})}
                    />
                </Row>
            ) : null}

            <div className="mb-4 flex flex-col gap-2">
                <LabeledControl label={t('builder.field.required', 'Required')}>
                    <Switch
                        checked={!!field.required}
                        disabled={disabled}
                        onCheckedChange={required =>
                            set({required: required || undefined})
                        }
                    />
                </LabeledControl>
                <LabeledControl
                    label={t('builder.field.hidden', 'Hidden')}
                    description={t(
                        'builder.field.hidden_help',
                        'Not shown: its default value is always sent.'
                    )}
                >
                    <Switch
                        checked={!!field.hidden}
                        disabled={disabled}
                        onCheckedChange={hidden =>
                            set({hidden: hidden || undefined})
                        }
                    />
                </LabeledControl>
                {custom && field.type !== 'boolean' ? (
                    <LabeledControl
                        label={t('builder.field.multiple', 'Several values')}
                    >
                        <Switch
                            checked={!!field.multiple}
                            disabled={disabled}
                            onCheckedChange={multiple =>
                                set({
                                    multiple: multiple || undefined,
                                    defaultValue: undefined,
                                    widget: undefined,
                                })
                            }
                        />
                    </LabeledControl>
                ) : null}
            </div>

            {widgets.length > 1 ? (
                <Row
                    label={t('builder.field.widget', 'Display')}
                    htmlFor={ids.widget}
                >
                    <SimpleSelect<string>
                        id={ids.widget}
                        value={widgetOf(field)}
                        disabled={disabled}
                        onValueChange={widget =>
                            set({widget: widget as InputField['widget']})
                        }
                        options={widgets.map(w => ({
                            value: w,
                            label: t(`builder.widget.${w}`, w),
                        }))}
                    />
                </Row>
            ) : null}

            {field.type === 'choice' && custom ? (
                <ChoicesEditor
                    choices={field.choices ?? []}
                    locales={locales}
                    disabled={disabled}
                    onChange={choices => set({choices})}
                />
            ) : null}

            {TEXT_TYPES.includes(field.type) ? (
                <div className="grid grid-cols-2 gap-3">
                    <NumberRow
                        label={t('builder.field.min_length', 'Min. length')}
                        value={constraints.minLength}
                        disabled={disabled}
                        onChange={v => setConstraint('minLength', v)}
                    />
                    <NumberRow
                        label={t('builder.field.max_length', 'Max. length')}
                        value={constraints.maxLength}
                        disabled={disabled}
                        onChange={v => setConstraint('maxLength', v)}
                    />
                </div>
            ) : null}
            {field.type === 'number' ? (
                <div className="grid grid-cols-2 gap-3">
                    <NumberRow
                        label={t('builder.field.min', 'Minimum')}
                        value={constraints.min}
                        disabled={disabled}
                        onChange={v => setConstraint('min', v)}
                    />
                    <NumberRow
                        label={t('builder.field.max', 'Maximum')}
                        value={constraints.max}
                        disabled={disabled}
                        onChange={v => setConstraint('max', v)}
                    />
                </div>
            ) : null}

            <div className="rounded-md bg-muted/50 p-3">
                <Field
                    key={`${field.id}-${field.type}-${field.multiple}`}
                    field={{
                        ...field,
                        id: `${field.id}-default`,
                        hidden: false,
                        required: false,
                        label: {
                            _: t(
                                'builder.field.default_value',
                                'Default value'
                            ),
                        },
                        help: undefined,
                        // A default value picks among every value, whatever the display
                        widget:
                            hasOptions(field) && field.type !== 'choice'
                                ? 'autocomplete'
                                : field.widget,
                    }}
                    value={field.defaultValue ?? emptyValue(field)}
                    onChange={v =>
                        set({
                            defaultValue:
                                v === '' ||
                                (Array.isArray(v) && v.length === 0) ||
                                v === false
                                    ? undefined
                                    : (v as FormValue),
                        })
                    }
                    loadOptions={loadOptions}
                    disabled={disabled}
                />
            </div>
        </Panel>
    );
}

function Panel({children}: {children: ReactNode}) {
    return <div data-testid="builder-inspector">{children}</div>;
}

function Row({
    label,
    htmlFor,
    help,
    children,
}: {
    label: ReactNode;
    htmlFor?: string;
    help?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="mb-3">
            <Label htmlFor={htmlFor} className="mb-1">
                {label}
            </Label>
            {children}
            <FieldHelp>{help}</FieldHelp>
        </div>
    );
}

function NumberRow({
    label,
    value,
    onChange,
    disabled,
}: {
    label: string;
    value: number | null | undefined;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    const id = useId();

    return (
        <Row label={label} htmlFor={id}>
            <Input
                id={id}
                type="number"
                min={0}
                value={value ?? ''}
                disabled={disabled}
                onChange={e => onChange(e.target.value)}
            />
        </Row>
    );
}

function ChoicesEditor({
    choices,
    onChange,
    locales,
    disabled,
}: {
    choices: Choice[];
    onChange: (choices: Choice[]) => void;
    locales: string[];
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const update = (index: number, patch: Partial<Choice>) =>
        onChange(choices.map((c, i) => (i === index ? {...c, ...patch} : c)));

    return (
        <fieldset className="mb-4">
            <legend className="mb-1 text-sm font-medium">
                {t('builder.field.choices', 'Choices')}
            </legend>
            <ul className="flex flex-col gap-2">
                {choices.map((choice, i) => (
                    <li
                        key={i}
                        className="flex items-start gap-2 rounded-md border p-2"
                    >
                        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                            <Input
                                value={choice.value}
                                disabled={disabled}
                                aria-label={t(
                                    'builder.field.choice_value',
                                    'Value'
                                )}
                                placeholder={t(
                                    'builder.field.choice_value',
                                    'Value'
                                )}
                                className="font-mono"
                                onChange={e =>
                                    update(i, {value: e.target.value})
                                }
                            />
                            <LocalizedInput
                                value={choice.label}
                                locales={locales}
                                disabled={disabled}
                                placeholder={choice.value}
                                onChange={label => update(i, {label})}
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            disabled={disabled}
                            aria-label={t(
                                'builder.field.remove_choice',
                                'Remove the choice'
                            )}
                            onClick={() =>
                                onChange(choices.filter((_, j) => j !== i))
                            }
                        >
                            <Trash2Icon />
                        </Button>
                    </li>
                ))}
            </ul>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-2"
                disabled={disabled}
                onClick={() =>
                    onChange([
                        ...choices,
                        {value: `option_${choices.length + 1}`, label: {}},
                    ])
                }
            >
                <PlusIcon /> {t('builder.field.add_choice', 'Add a choice')}
            </Button>
        </fieldset>
    );
}
