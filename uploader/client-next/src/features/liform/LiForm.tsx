'use client';

import {ReactNode, useId} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronsUpDownIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {
    Input,
    Label,
    Textarea,
    FieldError,
    FieldHelp,
} from '@alchemy/next-ui/ui/input';
import {
    Checkbox,
    RadioGroup,
    RadioGroupItem,
    LabeledControl,
} from '@alchemy/next-ui/ui/controls';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {Button} from '@alchemy/next-ui/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@alchemy/next-ui/ui/overlays';
import {Badge} from '@alchemy/next-ui/ui/misc';
import {cn} from '@alchemy/next-ui/utils/cn';
import {
    fieldLabel,
    guessWidget,
    initialValues,
    isNumericWidget,
    oneOfValue,
    selectedOneOf,
    sortedProperties,
} from './schema';
import type {LiFormErrors, LiFormField, LiFormValues} from './types';

type FieldProps = {
    name: string;
    path: string;
    field: LiFormField;
    value: unknown;
    onChange: (value: unknown) => void;
    errors: LiFormErrors;
    required?: boolean;
    disabled?: boolean;
    /** Hides the label (items of an array) */
    bare?: boolean;
};

const EMPTY_CHOICE = '__liform_empty__';

/** Value of the enum at `index` of the option list, kept with its type */
function choiceOptions(field: LiFormField): {value: string; label: string}[] {
    return (field.enum ?? []).map((v, i) => ({
        value: String(i),
        label: field.enum_titles?.[i] ?? String(v),
    }));
}

function enumIndex(field: LiFormField, value: unknown): number {
    return (field.enum ?? []).findIndex(v => v === value);
}

function FieldShell({
    id,
    label,
    required,
    field,
    error,
    children,
    inline,
}: {
    id?: string;
    label: string;
    required?: boolean;
    field: LiFormField;
    error?: string[];
    children: ReactNode;
    inline?: boolean;
}) {
    return (
        <div className="mb-4" data-testid="liform-field">
            {label && !inline ? (
                <Label htmlFor={id} className="mb-1.5">
                    {label}
                    {required ? (
                        <span aria-hidden className="text-destructive">
                            *
                        </span>
                    ) : null}
                </Label>
            ) : null}
            {children}
            <FieldError>{error?.join(' ')}</FieldError>
            <FieldHelp>{field.description}</FieldHelp>
        </div>
    );
}

const inputTypes: Record<string, string> = {
    'string': 'text',
    'text': 'text',
    'email': 'email',
    'password': 'password',
    'search': 'search',
    'url': 'url',
    'color': 'color',
    'time': 'time',
    'date': 'date',
    'compatible-date': 'date',
    'datetime': 'datetime-local',
    'date-time': 'datetime-local',
    'compatible-datetime': 'datetime-local',
};

function InputField({widget, ...props}: FieldProps & {widget: string}) {
    const {
        name,
        path,
        field,
        value,
        onChange,
        errors,
        required,
        disabled,
        bare,
    } = props;
    const id = useId();
    const numeric = isNumericWidget(widget);
    const type = numeric ? 'number' : (inputTypes[widget] ?? 'text');
    const placeholder =
        typeof field.defaultValue === 'string' ? field.defaultValue : undefined;
    const input = (
        <Input
            id={id}
            name={path}
            type={type}
            required={required}
            disabled={disabled}
            placeholder={type === 'text' ? placeholder : undefined}
            step={
                widget === 'integer'
                    ? 1
                    : numeric
                      ? 'any'
                      : type === 'datetime-local' || type === 'time'
                        ? 1
                        : undefined
            }
            min={field.minimum}
            max={field.maximum}
            minLength={field.minLength}
            maxLength={field.maxLength}
            aria-invalid={errors[path] ? true : undefined}
            className={cn(type === 'color' && 'w-16 p-1')}
            value={value === undefined || value === null ? '' : String(value)}
            onChange={e => onChange(e.target.value)}
        />
    );
    const affix =
        widget === 'money' ? '€' : widget === 'percent' ? '%' : undefined;

    return (
        <FieldShell
            id={id}
            label={bare ? '' : fieldLabel(name, field)}
            required={required}
            field={field}
            error={errors[path]}
        >
            {affix ? (
                <div className="flex items-center gap-2">
                    {widget === 'money' ? (
                        <span className="text-sm text-muted-foreground">
                            {affix}
                        </span>
                    ) : null}
                    {input}
                    {widget === 'percent' ? (
                        <span className="text-sm text-muted-foreground">
                            {affix}
                        </span>
                    ) : null}
                </div>
            ) : (
                input
            )}
        </FieldShell>
    );
}

function TextareaField(props: FieldProps) {
    const {
        name,
        path,
        field,
        value,
        onChange,
        errors,
        required,
        disabled,
        bare,
    } = props;
    const id = useId();

    return (
        <FieldShell
            id={id}
            label={bare ? '' : fieldLabel(name, field)}
            required={required}
            field={field}
            error={errors[path]}
        >
            <Textarea
                id={id}
                name={path}
                required={required}
                disabled={disabled}
                aria-invalid={errors[path] ? true : undefined}
                placeholder={
                    typeof field.defaultValue === 'string'
                        ? field.defaultValue
                        : undefined
                }
                value={typeof value === 'string' ? value : ''}
                onChange={e => onChange(e.target.value)}
            />
        </FieldShell>
    );
}

function CheckboxField(props: FieldProps) {
    const {name, path, field, value, onChange, errors, required, disabled} =
        props;
    const label = fieldLabel(name, field);

    return (
        <FieldShell
            label={label}
            required={required}
            field={field}
            error={errors[path]}
            inline
        >
            <LabeledControl
                label={
                    <>
                        {label}
                        {required ? (
                            <span aria-hidden className="text-destructive">
                                {' '}
                                *
                            </span>
                        ) : null}
                    </>
                }
            >
                <Checkbox
                    name={path}
                    disabled={disabled}
                    checked={value === true}
                    aria-invalid={errors[path] ? true : undefined}
                    onCheckedChange={c => onChange(c === true)}
                />
            </LabeledControl>
        </FieldShell>
    );
}

function ChoiceField(props: FieldProps) {
    const {t} = useTranslation();
    const {
        name,
        path,
        field,
        value,
        onChange,
        errors,
        required,
        disabled,
        bare,
    } = props;
    const id = useId();
    const options = choiceOptions(field);
    const index = enumIndex(field, value);
    // No empty option when told so (`defaultValue: false`), nor for a
    // required field holding a value
    const allowEmpty =
        field.defaultValue !== false && !(required && index >= 0);
    const placeholder =
        typeof field.placeholder === 'string'
            ? field.placeholder
            : t('liform.choice.placeholder', 'Select…');

    return (
        <FieldShell
            id={id}
            label={bare ? '' : fieldLabel(name, field)}
            required={required}
            field={field}
            error={errors[path]}
        >
            <SimpleSelect<string>
                id={id}
                disabled={disabled}
                value={index >= 0 ? String(index) : undefined}
                placeholder={placeholder}
                options={[
                    ...(allowEmpty
                        ? [{value: EMPTY_CHOICE, label: placeholder}]
                        : []),
                    ...options,
                ]}
                onValueChange={v =>
                    onChange(
                        v === EMPTY_CHOICE ? undefined : field.enum?.[Number(v)]
                    )
                }
            />
        </FieldShell>
    );
}

function ChoiceExpandedField(props: FieldProps) {
    const {name, path, field, value, onChange, errors, required, disabled} =
        props;
    const index = enumIndex(field, value);

    return (
        <FieldShell
            label={fieldLabel(name, field)}
            required={required}
            field={field}
            error={errors[path]}
        >
            <RadioGroup
                name={path}
                disabled={disabled}
                value={index >= 0 ? String(index) : ''}
                onValueChange={v => onChange(field.enum?.[Number(v)])}
                className="flex flex-col gap-2"
            >
                {choiceOptions(field).map(o => (
                    <LabeledControl key={o.value} label={o.label}>
                        <RadioGroupItem value={o.value} />
                    </LabeledControl>
                ))}
            </RadioGroup>
        </FieldShell>
    );
}

function toggle(list: unknown[], item: unknown, on: boolean): unknown[] {
    const without = list.filter(v => v !== item);

    return on ? [...without, item] : without;
}

function MultipleChoiceField({
    expanded,
    ...props
}: FieldProps & {expanded: boolean}) {
    const {t} = useTranslation();
    const {name, path, field, value, onChange, errors, required, disabled} =
        props;
    const items = field.items ?? {};
    const selected = Array.isArray(value) ? value : [];
    const options = (items.enum ?? []).map((v, i) => ({
        value: v,
        label: items.enum_titles?.[i] ?? String(v),
    }));
    const checkboxes = (
        <div className="flex flex-col gap-2 p-1">
            {options.map((o, i) => (
                <LabeledControl key={i} label={o.label}>
                    <Checkbox
                        disabled={disabled}
                        checked={selected.includes(o.value)}
                        onCheckedChange={c =>
                            onChange(toggle(selected, o.value, c === true))
                        }
                    />
                </LabeledControl>
            ))}
        </div>
    );

    return (
        <FieldShell
            label={fieldLabel(name, field)}
            required={required}
            field={field}
            error={errors[path]}
        >
            {expanded ? (
                checkboxes
            ) : (
                <Popover>
                    <PopoverTrigger asChild>
                        <button
                            type="button"
                            disabled={disabled}
                            data-invalid={errors[path] ? true : undefined}
                            className="flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-input bg-transparent px-3 py-1 text-left text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/60 disabled:opacity-50 data-invalid:border-destructive"
                        >
                            {selected.length === 0 ? (
                                <span className="text-muted-foreground">
                                    {typeof field.placeholder === 'string'
                                        ? field.placeholder
                                        : t(
                                              'liform.choice.placeholder',
                                              'Select…'
                                          )}
                                </span>
                            ) : (
                                options
                                    .filter(o => selected.includes(o.value))
                                    .map((o, i) => (
                                        <Badge key={i} variant="secondary">
                                            {o.label}
                                        </Badge>
                                    ))
                            )}
                            <ChevronsUpDownIcon className="ml-auto size-4 opacity-50" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent
                        align="start"
                        className="max-h-72 w-(--radix-popover-trigger-width) overflow-auto"
                    >
                        {checkboxes}
                    </PopoverContent>
                </Popover>
            )}
        </FieldShell>
    );
}

function ArrayField(props: FieldProps) {
    const {t} = useTranslation();
    const {name, path, field, value, onChange, errors, required, disabled} =
        props;
    const list = Array.isArray(value) ? value : [];
    const items: LiFormField = field.items ?? {type: 'string'};
    const move = (from: number, to: number) => {
        const copy = [...list];
        const [item] = copy.splice(from, 1);
        copy.splice(to, 0, item);
        onChange(copy);
    };

    return (
        <FieldShell
            label={fieldLabel(name, field)}
            required={required}
            field={field}
            error={errors[path]}
        >
            <div className="flex flex-col gap-2" data-testid="liform-array">
                {list.map((item, i) => (
                    <div key={i} className="flex items-start gap-1">
                        <div className="min-w-0 flex-1 [&>[data-testid=liform-field]]:mb-0">
                            <Field
                                name={String(i)}
                                path={`${path}.${i}`}
                                field={items}
                                value={item}
                                errors={errors}
                                disabled={disabled}
                                bare
                                onChange={v => {
                                    const copy = [...list];
                                    copy[i] = v;
                                    onChange(copy);
                                }}
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            disabled={disabled || i === 0}
                            aria-label={t('liform.array.up', 'Move up')}
                            onClick={() => move(i, i - 1)}
                        >
                            <ArrowUpIcon />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            disabled={disabled || i === list.length - 1}
                            aria-label={t('liform.array.down', 'Move down')}
                            onClick={() => move(i, i + 1)}
                        >
                            <ArrowDownIcon />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            disabled={disabled}
                            aria-label={t('liform.array.remove', 'Remove')}
                            onClick={() =>
                                onChange(list.filter((_, j) => j !== i))
                            }
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={disabled}
                        onClick={() =>
                            onChange([...list, initialValues(items)])
                        }
                    >
                        <PlusIcon /> {t('liform.array.add', 'Add')}
                    </Button>
                </div>
            </div>
        </FieldShell>
    );
}

function ObjectFields({
    field,
    path,
    value,
    onChange,
    errors,
    disabled,
}: Omit<FieldProps, 'name'>) {
    const values = (
        value && typeof value === 'object' && !Array.isArray(value) ? value : {}
    ) as LiFormValues;
    const required = new Set(field.required ?? []);

    return (
        <>
            {sortedProperties(field).map(([childName, child]) => {
                const childPath = path ? `${path}.${childName}` : childName;

                return (
                    <Field
                        key={childName}
                        name={childName}
                        path={childPath}
                        field={child}
                        value={values[childName]}
                        errors={errors}
                        disabled={disabled}
                        required={required.has(childName)}
                        onChange={v => onChange({...values, [childName]: v})}
                    />
                );
            })}
        </>
    );
}

function ObjectField(props: FieldProps) {
    const {name, field, bare} = props;
    const label = bare ? '' : fieldLabel(name, field);

    return (
        <fieldset className="mb-4 rounded-md border p-3 pb-0">
            {label ? (
                <legend className="px-1 text-sm font-medium">{label}</legend>
            ) : null}
            <ObjectFields {...props} />
        </fieldset>
    );
}

function OneOfField(props: FieldProps) {
    const {name, field, value, onChange, disabled} = props;
    const id = useId();
    const options = field.oneOf ?? [];
    const values = (
        value && typeof value === 'object' && !Array.isArray(value) ? value : {}
    ) as LiFormValues;
    const index = selectedOneOf(field, values);
    const option = options[index];

    return (
        <div className="mb-4">
            <FieldShell id={id} label={fieldLabel(name, field)} field={field}>
                <SimpleSelect<string>
                    id={id}
                    disabled={disabled}
                    value={String(index)}
                    options={options.map((o, i) => ({
                        value: String(i),
                        label: o.title ?? String(i + 1),
                    }))}
                    onValueChange={v => onChange(oneOfValue(field, Number(v)))}
                />
            </FieldShell>
            {option ? <ObjectFields {...props} field={option} /> : null}
        </div>
    );
}

function Field(props: FieldProps) {
    const {t} = useTranslation();
    const widget = guessWidget(props.field);

    switch (widget) {
        case 'object':
            return <ObjectField {...props} />;
        case 'oneOf':
            return <OneOfField {...props} />;
        case 'textarea':
            return <TextareaField {...props} />;
        case 'boolean':
        case 'checkbox':
            return <CheckboxField {...props} />;
        case 'choice':
            return <ChoiceField {...props} />;
        case 'choice-expanded':
            return <ChoiceExpandedField {...props} />;
        case 'choice-multiple':
            return <MultipleChoiceField {...props} expanded={false} />;
        case 'choice-multiple-expanded':
            return <MultipleChoiceField {...props} expanded />;
        case 'array':
            return <ArrayField {...props} />;
        default:
            if (widget in inputTypes || isNumericWidget(widget)) {
                return <InputField {...props} widget={widget} />;
            }

            return (
                <div
                    className="mb-4 text-sm text-destructive"
                    data-testid="liform-unsupported"
                >
                    {t(
                        'liform.unsupported',
                        'Unsupported widget type "{{widget}}" for field {{name}}',
                        {widget, name: props.name}
                    )}
                </div>
            );
    }
}

/**
 * Renders a compiled LiForm schema (see `compileSchema`) as a controlled
 * form body: the values of the root object, edited in place.
 */
export function LiFormFields({
    schema,
    value,
    onChange,
    errors,
    disabled,
}: {
    schema: LiFormField;
    value: LiFormValues;
    onChange: (value: LiFormValues) => void;
    errors: LiFormErrors;
    disabled?: boolean;
}) {
    return (
        <div data-testid="liform">
            {errors[''] ? (
                <div className="mb-4 text-sm text-destructive" role="alert">
                    {errors[''].join(' ')}
                </div>
            ) : null}
            <ObjectFields
                path=""
                field={schema}
                value={value}
                errors={errors}
                disabled={disabled}
                onChange={v => onChange(v as LiFormValues)}
            />
        </div>
    );
}
