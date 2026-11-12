'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    AsyncCombobox,
    type ComboOption,
} from '@alchemy/next-ui/form/AsyncCombobox';
import {
    Checkbox,
    LabeledControl,
    RadioGroup,
    RadioGroupItem,
} from '@alchemy/next-ui/ui/controls';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {FieldError} from '@alchemy/next-ui/ui/input';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {localized, widgetOf} from '../definition';
import type {FieldOption, FieldOptionsLoader, InputField} from '../types';

/** Lists of values that are rendered expanded are loaded whole, up to this */
const MAX_EXPANDED_PAGES = 10;

const EMPTY = '__empty__';

function OptionLabel({option}: {option: FieldOption}) {
    return (
        <span className="inline-flex items-center gap-1.5">
            {option.color ? (
                <span
                    aria-hidden
                    className="size-2.5 shrink-0 rounded-full border"
                    style={{backgroundColor: option.color}}
                />
            ) : null}
            {option.emoji ? <span aria-hidden>{option.emoji}</span> : null}
            {option.label}
        </span>
    );
}

function toComboOption(option: FieldOption): ComboOption {
    return {
        value: option.value,
        label: option.label,
        render:
            option.color || option.emoji ? (
                <OptionLabel option={option} />
            ) : undefined,
    };
}

function asList(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map(String);
    }

    return value === undefined || value === null || value === ''
        ? []
        : [String(value)];
}

type Props = {
    id: string;
    field: InputField;
    value: unknown;
    onChange: (value: unknown) => void;
    loadOptions: FieldOptionsLoader;
    placeholder?: string;
    disabled?: boolean;
};

/** A field picking among values: custom choices, Databox entities or tags */
export function OptionsField(props: Props) {
    const {i18n} = useTranslation();
    const {field} = props;
    const widget = widgetOf(field);

    if (field.type === 'choice') {
        const options = (field.choices ?? []).map(c => ({
            value: c.value,
            label: localized(c.label, i18n.language, c.value),
        }));

        return widget === 'autocomplete' ? (
            <ComboField
                {...props}
                loadPage={async query => ({
                    member: options.filter(o =>
                        o.label.toLowerCase().includes(query.toLowerCase())
                    ),
                    hasMore: false,
                })}
                resolve={async ids =>
                    options.filter(o => ids.includes(o.value))
                }
            />
        ) : widget === 'select' && !field.multiple ? (
            <SelectField {...props} options={options} />
        ) : (
            <ExpandedField {...props} options={options} />
        );
    }

    if (widget === 'checkboxes' || widget === 'radios') {
        return <RemoteExpandedField {...props} />;
    }

    return (
        <ComboField
            {...props}
            loadPage={(query, page) => props.loadOptions(field, {query, page})}
            resolve={async ids =>
                (await props.loadOptions(field, {ids})).member
            }
        />
    );
}

function ComboField({
    id,
    field,
    value,
    onChange,
    placeholder,
    disabled,
    loadPage,
    resolve,
}: Props & {
    loadPage: (
        query: string,
        page: number
    ) => Promise<{member: FieldOption[]; hasMore: boolean}>;
    resolve: (ids: string[]) => Promise<FieldOption[]>;
}) {
    const common = {
        id,
        disabled,
        placeholder,
        queryKey: [
            'form-field-options',
            field.id,
            field.attribute?.entityListId ?? field.key,
        ],
        loadPage: async (
            query: string,
            _signal: AbortSignal,
            cursor?: string
        ) => {
            const page = cursor ? Number(cursor) : 1;
            const result = await loadPage(query, page);

            return {
                options: result.member.map(toComboOption),
                next: result.hasMore ? String(page + 1) : undefined,
            };
        },
        resolveValues: async (ids: string[]) =>
            (await resolve(ids)).map(toComboOption),
    };

    return field.multiple ? (
        <AsyncCombobox
            {...common}
            multiple
            value={asList(value)}
            onChange={v => onChange(v)}
        />
    ) : (
        <AsyncCombobox
            {...common}
            value={asList(value)[0]}
            onChange={v => onChange(v ?? '')}
        />
    );
}

function SelectField({
    id,
    value,
    onChange,
    placeholder,
    disabled,
    options,
    field,
}: Props & {options: FieldOption[]}) {
    return (
        <SimpleSelect<string>
            id={id}
            value={asList(value)[0] ?? EMPTY}
            disabled={disabled}
            placeholder={placeholder}
            onValueChange={v => onChange(v === EMPTY ? '' : v)}
            options={[
                ...(field.required
                    ? []
                    : [{value: EMPTY, label: placeholder ?? '—'}]),
                ...options.map(o => ({
                    value: o.value,
                    label: <OptionLabel option={o} />,
                    searchText: o.label,
                })),
            ]}
        />
    );
}

function ExpandedField({
    id,
    field,
    value,
    onChange,
    disabled,
    options,
}: Props & {options: FieldOption[]}) {
    const selected = asList(value);

    if (field.multiple) {
        return (
            <div id={id} role="group" className="flex flex-col gap-2">
                {options.map(o => (
                    <LabeledControl
                        key={o.value}
                        label={<OptionLabel option={o} />}
                    >
                        <Checkbox
                            checked={selected.includes(o.value)}
                            disabled={disabled}
                            onCheckedChange={checked =>
                                onChange(
                                    checked
                                        ? [...selected, o.value]
                                        : selected.filter(v => v !== o.value)
                                )
                            }
                        />
                    </LabeledControl>
                ))}
            </div>
        );
    }

    return (
        <RadioGroup
            id={id}
            value={selected[0] ?? ''}
            disabled={disabled}
            onValueChange={v => onChange(v)}
            className="flex flex-col gap-2"
        >
            {options.map(o => (
                <LabeledControl
                    key={o.value}
                    label={<OptionLabel option={o} />}
                >
                    <RadioGroupItem value={o.value} />
                </LabeledControl>
            ))}
        </RadioGroup>
    );
}

function RemoteExpandedField(props: Props) {
    const {field, loadOptions} = props;
    const options = useQuery({
        queryKey: [
            'form-field-options',
            field.id,
            field.attribute?.entityListId ?? field.key,
            'all',
        ],
        queryFn: async () => {
            const all: FieldOption[] = [];
            for (let page = 1; page <= MAX_EXPANDED_PAGES; page++) {
                const result = await loadOptions(field, {page});
                all.push(...result.member);
                if (!result.hasMore) {
                    break;
                }
            }

            return all;
        },
        staleTime: 60_000,
    });

    if (options.isPending) {
        return <Spinner />;
    }
    if (options.error) {
        return <FieldError>{getErrorMessage(options.error)}</FieldError>;
    }

    return <ExpandedField {...props} options={options.data} />;
}
