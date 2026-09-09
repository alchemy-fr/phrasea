'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    CheckIcon,
    ChevronsUpDownIcon,
    PlusIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import type {AttributeDefinitionOrBuiltIn, AttributeType} from '@/types/api';
import {
    AQLCondition,
    AQLExpression,
    AQLLogical,
    AQLLogicalExpression,
    AQLOperator,
    AQLValueExpr,
    isCondition,
    isField,
    isLiteral,
    isLogical,
} from '../aql/types';
import {operatorLabels, valueToString} from '../aql/serializer';
import {parseAQL} from '../aql/parser';
import {
    getOperatorArgNames,
    getOperatorArity,
    getOperatorsForType,
    rawTypeMap,
} from '../aql/validation';
import {RawType} from '../aql/types';
import type {DefinitionsIndex} from '@/features/attributes/definitionsStore';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {cn} from '@/lib/utils/cn';

export function emptyCondition(): AQLCondition {
    return {
        leftOperand: {field: ''},
        operator: AQLOperator.EQ,
        rightOperand: {literal: ''},
    };
}

/** Ensures the root is a logical group so conditions can be appended */
export function normalizeExpression(
    expression: AQLExpression
): AQLLogicalExpression {
    if (isLogical(expression) && expression.operator !== AQLLogical.NOT) {
        return expression;
    }

    return {operator: AQLLogical.AND, conditions: [expression]};
}

type BuilderProps = {
    expression: AQLExpression;
    onChange: (expression: AQLExpression) => void;
    definitions: DefinitionsIndex;
    root?: boolean;
    onRemove?: () => void;
};

export function ExpressionBuilder({
    expression,
    onChange,
    definitions,
    root,
    onRemove,
}: BuilderProps) {
    const {t} = useTranslation();

    if (!isLogical(expression)) {
        return (
            <ConditionRow
                condition={expression}
                definitions={definitions}
                onChange={onChange}
                onRemove={onRemove}
            />
        );
    }

    const group = expression;
    const update = (conditions: AQLExpression[]) =>
        onChange({...group, conditions});

    return (
        <div
            className={cn(
                'space-y-2',
                !root && 'rounded-md border border-dashed p-3 pl-4'
            )}
        >
            <div className="flex items-center gap-2">
                {group.operator === AQLLogical.NOT ? (
                    <span className="text-xs font-semibold text-muted-foreground">
                        NOT
                    </span>
                ) : (
                    <SimpleSelect
                        size="sm"
                        className="w-24"
                        value={group.operator}
                        onValueChange={op =>
                            onChange({...group, operator: op as AQLLogical})
                        }
                        options={[
                            {
                                value: AQLLogical.AND,
                                label: t('aql.and', 'All (AND)'),
                            },
                            {
                                value: AQLLogical.OR,
                                label: t('aql.or', 'Any (OR)'),
                            },
                        ]}
                    />
                )}
                <span className="text-xs text-muted-foreground">
                    {t(
                        'search.condition.group_help',
                        'of the following conditions match'
                    )}
                </span>
                {!root && onRemove ? (
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        className="ml-auto"
                        onClick={onRemove}
                        aria-label={t('common.remove', 'Remove')}
                    >
                        <XIcon />
                    </Button>
                ) : null}
            </div>
            <div className="space-y-2">
                {group.conditions.map((c, i) => (
                    <ExpressionBuilder
                        key={i}
                        expression={c}
                        definitions={definitions}
                        onChange={next =>
                            update(
                                group.conditions.map((x, j) =>
                                    j === i ? next : x
                                )
                            )
                        }
                        onRemove={() =>
                            update(group.conditions.filter((_, j) => j !== i))
                        }
                    />
                ))}
            </div>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        update([...group.conditions, emptyCondition()])
                    }
                >
                    <PlusIcon /> {t('search.condition.add', 'Add condition')}
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        update([
                            ...group.conditions,
                            {
                                operator:
                                    group.operator === AQLLogical.AND
                                        ? AQLLogical.OR
                                        : AQLLogical.AND,
                                conditions: [emptyCondition()],
                            },
                        ])
                    }
                >
                    <PlusIcon />{' '}
                    {t('search.condition.add_group', 'Add condition group')}
                </Button>
            </div>
        </div>
    );
}

function ConditionRow({
    condition,
    definitions,
    onChange,
    onRemove,
}: {
    condition: AQLCondition;
    definitions: DefinitionsIndex;
    onChange: (c: AQLCondition) => void;
    onRemove?: () => void;
}) {
    const {t} = useTranslation();
    const field = isField(condition.leftOperand)
        ? condition.leftOperand.field
        : '';
    const definition = definitions[field];
    const operators = getOperatorsForType(definition?.type);
    const arity = getOperatorArity(condition.operator);
    const argNames = getOperatorArgNames(condition.operator);
    const values: AQLValueExpr[] =
        condition.rightOperand === undefined
            ? []
            : Array.isArray(condition.rightOperand)
              ? condition.rightOperand
              : [condition.rightOperand];

    const setOperator = (operator: AQLOperator) => {
        const a = getOperatorArity(operator);
        let rightOperand: AQLCondition['rightOperand'];
        if (a === 0) {
            rightOperand = undefined;
        } else if (a === true) {
            rightOperand = values.length > 0 ? values : [{literal: ''}];
        } else if (a === 1) {
            rightOperand = values[0] ?? {literal: ''};
        } else {
            rightOperand = Array.from(
                {length: a},
                (_, i) => values[i] ?? {literal: ''}
            );
        }
        onChange({...condition, operator, rightOperand});
    };

    const setValue = (index: number, value: AQLValueExpr) => {
        if (arity === 1) {
            onChange({...condition, rightOperand: value});
        } else {
            const next = [...values];
            next[index] = value;
            onChange({...condition, rightOperand: next});
        }
    };

    return (
        <div className="flex flex-wrap items-start gap-2 rounded-md bg-muted/40 p-2">
            <FieldPicker
                value={field}
                definitions={definitions}
                onChange={slug => {
                    const def = definitions[slug];
                    const ops = getOperatorsForType(def?.type);
                    const operator = ops.includes(condition.operator)
                        ? condition.operator
                        : ops[0];
                    onChange({
                        ...condition,
                        leftOperand: {field: slug},
                        operator,
                    });
                    if (operator !== condition.operator) {
                        setTimeout(() => setOperator(operator), 0);
                    }
                }}
            />
            <SimpleSelect
                size="sm"
                className="w-44"
                value={condition.operator}
                onValueChange={op => setOperator(op as AQLOperator)}
                options={operators.map(op => ({
                    value: op,
                    label: operatorLabels[op],
                }))}
            />
            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                {arity === 0 ? null : arity === true ? (
                    <>
                        {values.map((v, i) => (
                            <div key={i} className="flex items-center gap-1">
                                <ValueInput
                                    value={v}
                                    type={definition?.type}
                                    onChange={nv => setValue(i, nv)}
                                />
                                {values.length > 1 ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-xs"
                                        onClick={() =>
                                            onChange({
                                                ...condition,
                                                rightOperand: values.filter(
                                                    (_, j) => j !== i
                                                ),
                                            })
                                        }
                                    >
                                        <XIcon />
                                    </Button>
                                ) : null}
                            </div>
                        ))}
                        <Button
                            variant="outline"
                            size="icon-sm"
                            onClick={() =>
                                onChange({
                                    ...condition,
                                    rightOperand: [...values, {literal: ''}],
                                })
                            }
                        >
                            <PlusIcon />
                        </Button>
                    </>
                ) : (
                    Array.from({length: arity}, (_, i) => (
                        <ValueInput
                            key={i}
                            value={values[i] ?? {literal: ''}}
                            type={definition?.type}
                            label={argNames?.[i]}
                            onChange={nv => setValue(i, nv)}
                        />
                    ))
                )}
            </div>
            {onRemove ? (
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={onRemove}
                    aria-label={t('common.remove', 'Remove')}
                >
                    <Trash2Icon />
                </Button>
            ) : null}
        </div>
    );
}

function FieldPicker({
    value,
    definitions,
    onChange,
}: {
    value: string;
    definitions: DefinitionsIndex;
    onChange: (slug: string) => void;
}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const list = useMemo(() => {
        const defs = Object.values(definitions).filter(
            d => d.builtIn || d.searchable
        );
        const builtIn = defs
            .filter(d => d.builtIn)
            .sort((a, b) => a.name.localeCompare(b.name));
        const others = defs
            .filter(d => !d.builtIn)
            .sort((a, b) =>
                (a.displayName ?? a.name).localeCompare(b.displayName ?? b.name)
            );

        return {builtIn, others};
    }, [definitions]);
    const current = definitions[value];

    const item = (d: AttributeDefinitionOrBuiltIn) => (
        <CommandItem
            key={d.searchSlug}
            value={`${d.displayName ?? d.name} ${d.searchSlug}`}
            onSelect={() => {
                onChange(d.searchSlug);
                setOpen(false);
            }}
        >
            <CheckIcon
                className={cn(
                    'size-4',
                    d.searchSlug === value ? 'opacity-100' : 'opacity-0'
                )}
            />
            <span
                className={cn('flex-1 truncate', d.builtIn && 'font-semibold')}
            >
                {d.displayName ?? d.name}
            </span>
            <span className="text-xs text-muted-foreground">{d.type}</span>
        </CommandItem>
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    role="combobox"
                    className={cn(
                        'w-52 justify-between font-normal',
                        !current && 'text-muted-foreground'
                    )}
                >
                    <span className="truncate">
                        {current
                            ? (current.displayName ?? current.name)
                            : t(
                                  'search.condition.select_field',
                                  'Select a field…'
                              )}
                    </span>
                    <ChevronsUpDownIcon className="opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-72 p-0" align="start">
                <Command>
                    <CommandInput placeholder={t('common.search', 'Search…')} />
                    <CommandList>
                        <CommandEmpty>
                            {t('common.no_match', 'No match')}
                        </CommandEmpty>
                        <CommandGroup
                            heading={t('search.condition.built_in', 'Built-in')}
                        >
                            {list.builtIn.map(item)}
                        </CommandGroup>
                        <CommandGroup
                            heading={t(
                                'search.condition.attributes',
                                'Attributes'
                            )}
                        >
                            {list.others.map(item)}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

/**
 * Typed value input. A leading `=` switches to a raw AQL expression
 * (functions, arithmetic, fields); quotes force a string.
 */
function ValueInput({
    value,
    type,
    label,
    onChange,
}: {
    value: AQLValueExpr;
    type: AttributeType | undefined;
    label?: string;
    onChange: (value: AQLValueExpr) => void;
}) {
    const raw = type ? rawTypeMap[type] : undefined;
    const text = valueToInput(value);

    if (raw === RawType.Boolean) {
        return (
            <SimpleSelect
                size="sm"
                className="w-28"
                value={
                    value === true
                        ? 'true'
                        : value === false
                          ? 'false'
                          : value === null
                            ? 'null'
                            : 'true'
                }
                onValueChange={v =>
                    onChange(v === 'true' ? true : v === 'false' ? false : null)
                }
                options={[
                    {value: 'true', label: 'Yes'},
                    {value: 'false', label: 'No'},
                    {value: 'null', label: 'Null'},
                ]}
            />
        );
    }

    const inputType =
        raw === RawType.Number
            ? 'number'
            : raw === RawType.Date
              ? 'date'
              : raw === RawType.DateTime
                ? 'datetime-local'
                : 'text';

    return (
        <div className="flex items-center gap-1">
            {label ? (
                <span className="text-xs text-muted-foreground">{label}</span>
            ) : null}
            <Input
                className="h-8 w-44 text-sm"
                type={text.startsWith('=') ? 'text' : inputType}
                value={text}
                placeholder={raw === RawType.GeoPoint ? 'lat' : undefined}
                onChange={e => onChange(inputToValue(e.target.value, raw))}
            />
        </div>
    );
}

function valueToInput(value: AQLValueExpr): string {
    if (value === null) return 'null';
    if (typeof value === 'boolean') return String(value);
    if (typeof value === 'number') return String(value);
    if (isLiteral(value)) return value.literal;

    // complex expression (function, arithmetic, field): raw mode
    return `=${valueToString(value)}`;
}

function inputToValue(input: string, raw: RawType | undefined): AQLValueExpr {
    if (input.startsWith('=')) {
        const ast = parseAQL(`x = ${input.slice(1)}`);
        if (
            ast &&
            isCondition(ast.expression) &&
            ast.expression.rightOperand !== undefined &&
            !Array.isArray(ast.expression.rightOperand)
        ) {
            return ast.expression.rightOperand;
        }

        return {literal: input};
    }
    if (/^".*"$/.test(input)) {
        return {literal: input.slice(1, -1)};
    }
    if (raw === RawType.Number) {
        const n = parseFloat(input);

        return Number.isNaN(n) ? {literal: input} : n;
    }
    if (raw === RawType.DateTime && input) {
        const d = new Date(input);

        return {literal: Number.isNaN(d.getTime()) ? input : d.toISOString()};
    }

    return {literal: input};
}
