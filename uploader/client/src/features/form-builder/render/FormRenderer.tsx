'use client';

import {ReactNode, useId} from 'react';
import {useTranslation} from 'react-i18next';
import {
    FieldError,
    FieldHelp,
    Input,
    Label,
    Textarea,
} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {fieldLabel, hasOptions, localized, widgetOf} from '../definition';
import type {
    BuilderField,
    FieldOptionsLoader,
    FormDefinition,
    FormFieldErrors,
    FormValues,
    InputField,
} from '../types';
import {MultiValueInput} from './MultiValueInput';
import {OptionsField} from './OptionsField';

type Props = {
    definition: FormDefinition;
    values: FormValues;
    onChange: (values: FormValues) => void;
    errors: FormFieldErrors;
    loadOptions: FieldOptionsLoader;
    disabled?: boolean;
};

/** The fields of a builder form, as users fill them */
export function FormRenderer({
    definition,
    values,
    onChange,
    errors,
    loadOptions,
    disabled,
}: Props) {
    return (
        <div data-testid="builder-form">
            {errors[''] ? (
                <FieldError>{errors[''].join(' ')}</FieldError>
            ) : null}
            {definition.fields.map(field => (
                <Field
                    key={field.id}
                    field={field}
                    value={
                        field.source === 'layout'
                            ? undefined
                            : values[field.key]
                    }
                    onChange={v =>
                        field.source !== 'layout' &&
                        onChange({...values, [field.key]: v})
                    }
                    errors={
                        field.source === 'layout'
                            ? undefined
                            : errors[field.key]
                    }
                    loadOptions={loadOptions}
                    disabled={disabled}
                />
            ))}
        </div>
    );
}

export function Field({
    field,
    value,
    onChange,
    errors,
    loadOptions,
    disabled,
}: {
    field: BuilderField;
    value: unknown;
    onChange: (value: unknown) => void;
    errors?: string[];
    loadOptions: FieldOptionsLoader;
    disabled?: boolean;
}) {
    const {i18n} = useTranslation();
    const id = useId();
    const lang = i18n.language;

    if (field.source === 'layout') {
        const text = localized(field.label, lang);

        return field.kind === 'section' ? (
            <h3 className="mt-6 mb-3 border-b pb-1 text-base font-semibold first:mt-0">
                {text}
            </h3>
        ) : (
            <p className="mb-4 text-sm whitespace-pre-line text-muted-foreground">
                {text}
            </p>
        );
    }
    if (field.hidden) {
        return null;
    }

    const label = fieldLabel(field, lang);
    const help = localized(field.help, lang);
    const placeholder = localized(field.placeholder, lang) || undefined;

    if (field.type === 'boolean') {
        const Control = widgetOf(field) === 'switch' ? Switch : Checkbox;

        return (
            <Shell field={field} errors={errors} help={help}>
                <LabeledControl
                    label={
                        <>
                            {label}
                            <RequiredMark field={field} />
                        </>
                    }
                >
                    <Control
                        id={id}
                        checked={value === true}
                        disabled={disabled}
                        aria-invalid={errors ? true : undefined}
                        onCheckedChange={checked => onChange(checked === true)}
                    />
                </LabeledControl>
            </Shell>
        );
    }

    return (
        <Shell field={field} errors={errors} help={help}>
            <Label htmlFor={id} className="mb-1.5">
                {label}
                <RequiredMark field={field} />
            </Label>
            <Widget
                id={id}
                field={field}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                invalid={!!errors}
                loadOptions={loadOptions}
                disabled={disabled}
            />
        </Shell>
    );
}

function RequiredMark({field}: {field: InputField}) {
    return field.required ? (
        <span aria-hidden className="text-destructive">
            *
        </span>
    ) : null;
}

function Shell({
    field,
    errors,
    help,
    children,
}: {
    field: InputField;
    errors?: string[];
    help?: string;
    children: ReactNode;
}) {
    return (
        <div className="mb-4" data-testid="builder-field" data-key={field.key}>
            {children}
            <FieldError>{errors?.join(' ')}</FieldError>
            <FieldHelp>{help}</FieldHelp>
        </div>
    );
}

const inputTypes: Partial<Record<InputField['type'], string>> = {
    text: 'text',
    number: 'number',
    date: 'date',
    date_time: 'datetime-local',
    color: 'color',
};

function Widget({
    id,
    field,
    value,
    onChange,
    placeholder,
    invalid,
    loadOptions,
    disabled,
}: {
    id: string;
    field: InputField;
    value: unknown;
    onChange: (value: unknown) => void;
    placeholder?: string;
    invalid: boolean;
    loadOptions: FieldOptionsLoader;
    disabled?: boolean;
}) {
    if (hasOptions(field)) {
        return (
            <OptionsField
                id={id}
                field={field}
                value={value}
                onChange={onChange}
                loadOptions={loadOptions}
                placeholder={placeholder}
                disabled={disabled}
            />
        );
    }

    if (field.multiple) {
        return (
            <MultiValueInput
                id={id}
                type={inputTypes[field.type] ?? 'text'}
                value={Array.isArray(value) ? value.map(String) : []}
                onChange={onChange}
                placeholder={placeholder}
                invalid={invalid}
                disabled={disabled}
            />
        );
    }

    const text = value === undefined || value === null ? '' : String(value);

    if (field.type === 'textarea') {
        return (
            <Textarea
                id={id}
                value={text}
                placeholder={placeholder}
                disabled={disabled}
                maxLength={field.constraints?.maxLength ?? undefined}
                aria-invalid={invalid || undefined}
                onChange={e => onChange(e.target.value)}
            />
        );
    }

    if (field.type === 'color') {
        return (
            <div className="flex items-center gap-2">
                <Input
                    type="color"
                    aria-label={field.key}
                    value={text || '#000000'}
                    disabled={disabled}
                    className="h-9 w-12 p-1"
                    onChange={e => onChange(e.target.value)}
                />
                <Input
                    id={id}
                    value={text}
                    placeholder={placeholder ?? '#rrggbb'}
                    disabled={disabled}
                    aria-invalid={invalid || undefined}
                    className="w-32"
                    onChange={e => onChange(e.target.value)}
                />
            </div>
        );
    }

    return (
        <Input
            id={id}
            type={inputTypes[field.type] ?? 'text'}
            value={text}
            placeholder={placeholder}
            disabled={disabled}
            min={field.constraints?.min ?? undefined}
            max={field.constraints?.max ?? undefined}
            maxLength={field.constraints?.maxLength ?? undefined}
            aria-invalid={invalid || undefined}
            onChange={e => onChange(e.target.value)}
        />
    );
}
