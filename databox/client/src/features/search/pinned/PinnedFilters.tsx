'use client';

import {FormEvent, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {CheckIcon, ChevronDownIcon, XIcon} from 'lucide-react';
import {
    AttributeType,
    EntityName,
    FilterWidget,
    ProfileItemSection,
    ProfileItemType,
    type AttributeDefinition,
    type AttributeDefinitionOrBuiltIn,
    type AttributeEntity,
    type ProfileItem,
} from '@/types/api';
import {useProfileStore} from '@/features/profiles/profileStore';
import {
    aqlKey,
    useDefinitionsById,
} from '@/features/attributes/definitionsStore';
import {entityLabel} from '@/features/attributes/types/registry';
import {getAttributeEntities} from '@/lib/api/metadata';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {RadioGroup, RadioGroupItem} from '@/components/ui/controls';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {InlineLoader} from '@/components/ui/loader';
import {cn} from '@/lib/utils/cn';
import {idFromIri, iri} from '@/lib/utils/iri';
import {useSearch} from '../SearchProvider';
import {useEntitiesStore} from '../entitiesStore';
import {parseAQL} from '../aql/parser';
import {conditionToString, ConditionBuilder} from '../aql/serializer';
import {
    AQLCondition,
    AQLOperator,
    AQLQueryAST,
    RawType,
    isLiteral,
} from '../aql/types';
import {
    getOperatorArity,
    getOperatorsForType,
    rawTypeMap,
} from '../aql/validation';
import {
    HumanizedExpression,
    InversedExpression,
} from '../conditions/SearchConditionChip';
import {ConditionRow, fitValues} from '../conditions/ConditionBuilder';
import {
    conditionsOn,
    dateRangeOf,
    dateRangeQuery,
    pinnedConditionId,
    type DateRange,
} from './pinnedFilterQuery';
import {booleanChoices} from './booleanChoices';
import {ExploreEntityListButton} from '@/features/entities/explore/ExploreEntityListButton';

export type PinnedFilter = {
    item: ProfileItem;
    definition: AttributeDefinitionOrBuiltIn;
    /** AQL field of the attribute */
    field: string;
    conditionId: string;
};

/** The search filters pinned by the current display profile */
export function usePinnedFilters(): PinnedFilter[] {
    const items = useProfileStore(s => s.current?.items);
    const ids = useMemo(
        () =>
            (items ?? [])
                .filter(
                    i =>
                        i.section === ProfileItemSection.Filters &&
                        i.type === ProfileItemType.Definition
                )
                .map(i => i.definition)
                .filter((id): id is string => !!id),
        [items]
    );
    const byId = useDefinitionsById({ids});

    return useMemo(
        () =>
            (items ?? [])
                .filter(i => i.section === ProfileItemSection.Filters)
                .flatMap(item => {
                    const definition =
                        byId[
                            (item.type === ProfileItemType.BuiltIn
                                ? item.key
                                : item.definition) ?? ''
                        ];
                    if (!definition) {
                        return [];
                    }
                    const field = aqlKey(definition);

                    return [
                        {
                            item,
                            definition,
                            field,
                            conditionId: pinnedConditionId(field),
                        },
                    ];
                }),
        [items, byId]
    );
}

/** Applies the query of a filter (none clears it), closes the picker unless `keepOpen` */
export type ApplyFilter = (
    query: string | undefined,
    keepOpen?: boolean
) => void;

export type WidgetProps = {
    filter: PinnedFilter;
    /** The condition the filter currently applies */
    ast: AQLQueryAST | undefined;
    onApply: ApplyFilter;
    /** Closes the picker the widget sits in, if any */
    onClose?: () => void;
};

const isBoolean = (filter: PinnedFilter) =>
    rawTypeMap[filter.definition.type] === RawType.Boolean;

/** The condition a pinned filter controls, and the way to change it */
function usePinnedCondition(filter: PinnedFilter) {
    const search = useSearch();
    const condition = search.conditions.find(c => c.id === filter.conditionId);
    const ast = useMemo(
        () => (condition ? parseAQL(condition.query) : undefined),
        [condition]
    );

    return {
        condition,
        ast,
        apply: (query: string | undefined) => {
            if (query) {
                search.upsertCondition({id: filter.conditionId, query});
            } else {
                search.removeCondition(filter.conditionId);
            }
        },
    };
}

/** The boolean value a filter applies, if it applies a single one */
function booleanValueOf(
    filter: PinnedFilter,
    ast: AQLQueryAST | undefined
): boolean | undefined {
    const values = ConditionBuilder.fromQuery(filter.field, ast).getValues();

    return values.length === 1 && typeof values[0] === 'boolean'
        ? values[0]
        : undefined;
}

const booleanQuery = (filter: PinnedFilter, value: boolean) =>
    new ConditionBuilder(filter.field, [value]).toString();

export function PinnedFilterChip({filter}: {filter: PinnedFilter}) {
    return isBoolean(filter) && filter.item.widget === FilterWidget.Inline ? (
        <InlineBooleanFilter filter={filter} />
    ) : (
        <PopoverFilterChip filter={filter} />
    );
}

/**
 * A pinned filter: the attribute name until a value is picked, then the
 * condition it applies. Clicking it opens a value picker suited to the
 * attribute type.
 */
function PopoverFilterChip({filter}: {filter: PinnedFilter}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const {condition, ast, apply: applyQuery} = usePinnedCondition(filter);
    const label = filter.definition.displayName ?? filter.definition.name;
    const booleanValue = isBoolean(filter)
        ? booleanValueOf(filter, ast)
        : undefined;

    const apply: ApplyFilter = (query, keepOpen) => {
        applyQuery(query);
        if (!keepOpen) {
            setOpen(false);
        }
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div
                data-testid="pinned-filter"
                data-field={filter.field}
                data-active={condition ? 'true' : undefined}
                className={cn(
                    'inline-flex h-7 max-w-full items-center gap-1 rounded-full border text-xs transition-colors',
                    condition ? 'pr-1 pl-2.5' : 'px-2.5',
                    !condition &&
                        'border-dashed border-foreground/25 text-muted-foreground hover:border-foreground/40 hover:text-foreground',
                    condition?.disabled &&
                        'border-warning/60 bg-warning/10 text-muted-foreground line-through decoration-warning',
                    condition &&
                        !condition.disabled &&
                        (condition.inversed
                            ? 'border-destructive/40 bg-destructive/10 text-foreground hover:bg-destructive/15'
                            : 'border-primary/30 bg-primary/10 text-foreground hover:bg-primary/15')
                )}
            >
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        className="inline-flex min-w-0 items-center gap-1"
                    >
                        {condition ? (
                            <span className="min-w-0 truncate">
                                <InversedExpression
                                    inversed={condition.inversed}
                                >
                                    {booleanValue !== undefined ? (
                                        <>
                                            <span className="font-medium">
                                                {label}
                                            </span>
                                            {': '}
                                            {
                                                booleanChoices(
                                                    filter.item,
                                                    t
                                                ).find(
                                                    c =>
                                                        c.value === booleanValue
                                                )!.label
                                            }
                                        </>
                                    ) : ast ? (
                                        <HumanizedExpression
                                            expression={ast.expression}
                                        />
                                    ) : (
                                        condition.query
                                    )}
                                </InversedExpression>
                            </span>
                        ) : (
                            <>
                                <span className="min-w-0 truncate">
                                    {label}
                                </span>
                                <ChevronDownIcon className="size-3.5 shrink-0 opacity-60" />
                            </>
                        )}
                    </button>
                </PopoverTrigger>
                {condition ? (
                    <button
                        type="button"
                        className="rounded-full p-0.5 text-muted-foreground opacity-60 hover:bg-foreground/10 hover:opacity-100"
                        aria-label={t('facets.clear_filter', 'Clear filter')}
                        title={t('facets.clear_filter', 'Clear filter')}
                        onClick={() => apply(undefined)}
                    >
                        <XIcon className="size-3.5" />
                    </button>
                ) : null}
            </div>
            <PopoverContent
                align="start"
                className="w-auto p-0"
                data-testid="pinned-filter-picker"
            >
                <div className="border-b px-3 py-2 text-xs font-semibold text-muted-foreground">
                    {label}
                </div>
                <PinnedFilterWidget
                    filter={filter}
                    // A disabled condition is picked again from scratch
                    ast={condition?.disabled ? undefined : ast}
                    onApply={apply}
                    onClose={() => setOpen(false)}
                />
            </PopoverContent>
        </Popover>
    );
}

export function PinnedFilterWidget(props: WidgetProps) {
    const type = props.filter.definition.type;
    const raw = rawTypeMap[type];

    if (raw === RawType.Boolean) {
        return <BooleanFilter {...props} />;
    }
    if (type === AttributeType.Entity) {
        return <EntityFilter {...props} />;
    }
    if (raw === RawType.Date || raw === RawType.DateTime) {
        return <DateFilter {...props} />;
    }

    return <ConditionFilter {...props} />;
}

/**
 * Laid out in the search bar: radio buttons, without the attribute name
 * when both values are labelled (e.g. "Assets / Stories").
 */
function InlineBooleanFilter({filter}: {filter: PinnedFilter}) {
    const {t} = useTranslation();
    const {condition, ast, apply} = usePinnedCondition(filter);
    const active = !!condition && !condition.disabled;
    // An inverted condition (from a URL) checks no value
    const value =
        active && !condition.inversed ? booleanValueOf(filter, ast) : undefined;
    const label = filter.definition.displayName ?? filter.definition.name;
    const showLabel = !filter.item.trueLabel || !filter.item.falseLabel;

    return (
        <div
            data-testid="pinned-filter"
            data-field={filter.field}
            data-active={condition ? 'true' : undefined}
            title={showLabel ? undefined : label}
            className={cn(
                'inline-flex h-7 max-w-full items-center gap-2 rounded-full border pl-2.5 text-xs transition-colors',
                condition ? 'pr-1' : 'pr-2.5',
                active
                    ? 'border-primary/30 bg-primary/10'
                    : 'border-foreground/20'
            )}
        >
            {showLabel ? (
                <span className="truncate font-medium text-muted-foreground">
                    {label}
                </span>
            ) : null}
            <RadioGroup
                className="flex items-center gap-3"
                aria-label={label}
                value={value === undefined ? '' : String(value)}
                onValueChange={v => apply(booleanQuery(filter, v === 'true'))}
            >
                {booleanChoices(filter.item, t).map(c => (
                    <label
                        key={String(c.value)}
                        className="flex cursor-pointer items-center gap-1.5 whitespace-nowrap"
                    >
                        <RadioGroupItem
                            className="size-3.5"
                            value={String(c.value)}
                        />
                        {c.label}
                    </label>
                ))}
            </RadioGroup>
            {condition ? (
                <button
                    type="button"
                    className="rounded-full p-0.5 text-muted-foreground opacity-60 hover:bg-foreground/10 hover:opacity-100"
                    aria-label={t('facets.clear_filter', 'Clear filter')}
                    title={t('facets.clear_filter', 'Clear filter')}
                    onClick={() => apply(undefined)}
                >
                    <XIcon className="size-3.5" />
                </button>
            ) : null}
        </div>
    );
}

/** The values, as a menu or as radio buttons (see the profile item `widget`) */
function BooleanFilter({filter, ast, onApply}: WidgetProps) {
    const {t} = useTranslation();
    const value = booleanValueOf(filter, ast);
    const queryOf = (v: boolean) => booleanQuery(filter, v);
    const choices = booleanChoices(filter.item, t);

    if (filter.item.widget === FilterWidget.Radio) {
        return (
            <RadioGroup
                className="flex gap-4 px-3 py-2.5"
                value={value === undefined ? '' : String(value)}
                onValueChange={v => onApply(queryOf(v === 'true'), true)}
            >
                {choices.map(c => (
                    <label
                        key={String(c.value)}
                        className="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <RadioGroupItem value={String(c.value)} />
                        {c.label}
                    </label>
                ))}
            </RadioGroup>
        );
    }

    return (
        <div className="flex min-w-40 flex-col p-1" role="listbox">
            {choices.map(c => (
                <button
                    key={String(c.value)}
                    type="button"
                    role="option"
                    aria-selected={value === c.value}
                    className="flex items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                    onClick={() => onApply(queryOf(c.value))}
                >
                    <CheckIcon
                        className={cn(
                            'size-4',
                            value === c.value ? 'opacity-100' : 'opacity-0'
                        )}
                    />
                    {c.label}
                </button>
            ))}
        </div>
    );
}

function entityListId(
    definition: AttributeDefinitionOrBuiltIn
): string | undefined {
    const list = (definition as AttributeDefinition).entityList;
    if (!list) {
        return undefined;
    }

    return typeof list === 'string' ? idFromIri(list) : list.id;
}

/** The values of the entity list, searched; several can be picked */
function EntityFilter({filter, ast, onApply, onClose}: WidgetProps) {
    const {t} = useTranslation();
    const [query, setQuery] = useState('');
    const debounced = useDebouncedValue(query.trim());
    const listId = entityListId(filter.definition);
    const storeEntity = useEntitiesStore(s => s.store);
    const {data, isFetching} = useQuery({
        queryKey: ['pinned-filter-entities', listId, debounced],
        queryFn: () =>
            getAttributeEntities({
                list: listId,
                query: debounced || undefined,
            }),
        enabled: !!listId,
    });
    const selected = ConditionBuilder.fromQuery(filter.field, ast)
        .getValues()
        .filter((v): v is string => typeof v === 'string');

    const toggle = (entity: AttributeEntity) => {
        // Lets the chip name the value without fetching it
        storeEntity(iri(EntityName.AttributeEntity, entity.id), {...entity});
        const next = selected.includes(entity.id)
            ? selected.filter(id => id !== entity.id)
            : [...selected, entity.id];
        onApply(
            next.length > 0
                ? new ConditionBuilder(filter.field, next).toString()
                : undefined,
            true
        );
    };

    return (
        <Command shouldFilter={false} className="w-72">
            <CommandInput
                value={query}
                onValueChange={setQuery}
                placeholder={t('common.search', 'Search…')}
            />
            <CommandList>
                {isFetching && !data ? (
                    <div className="flex justify-center p-3">
                        <InlineLoader />
                    </div>
                ) : (
                    <CommandEmpty>
                        {t('common.no_match', 'No match')}
                    </CommandEmpty>
                )}
                {data?.items.map(entity => (
                    <CommandItem
                        key={entity.id}
                        value={entity.id}
                        onSelect={() => toggle(entity)}
                    >
                        <CheckIcon
                            className={cn(
                                'size-4',
                                selected.includes(entity.id)
                                    ? 'opacity-100'
                                    : 'opacity-0'
                            )}
                        />
                        <span className="truncate">{entityLabel(entity)}</span>
                    </CommandItem>
                ))}
            </CommandList>
            {listId ? (
                <div className="flex justify-end border-t p-1">
                    <ExploreEntityListButton
                        listId={listId}
                        compact
                        onNavigate={onClose}
                    />
                </div>
            ) : null}
        </Command>
    );
}

/** A day range, both days included, either one optional */
function DateFilter({filter, ast, onApply}: WidgetProps) {
    const {t} = useTranslation();
    const withTime = rawTypeMap[filter.definition.type] === RawType.DateTime;
    const [range, setRange] = useState<DateRange>(() =>
        dateRangeOf(filter.field, ast, withTime)
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        onApply(dateRangeQuery(filter.field, range, withTime));
    };

    return (
        <form className="space-y-2 p-3" onSubmit={submit}>
            <div className="grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-2 text-xs text-muted-foreground">
                <label htmlFor="pinned-filter-from">
                    {t('search.pinned.from', 'From')}
                </label>
                <Input
                    id="pinned-filter-from"
                    type="date"
                    className="h-8 w-44 text-sm"
                    value={range.from}
                    max={range.to || undefined}
                    onChange={e => setRange({...range, from: e.target.value})}
                />
                <label htmlFor="pinned-filter-to">
                    {t('search.pinned.to', 'To')}
                </label>
                <Input
                    id="pinned-filter-to"
                    type="date"
                    className="h-8 w-44 text-sm"
                    value={range.to}
                    min={range.from || undefined}
                    onChange={e => setRange({...range, to: e.target.value})}
                />
            </div>
            <FilterActions
                canClear={!!ast}
                onClear={() => onApply(undefined)}
            />
        </form>
    );
}

/** Operator of a new condition: "contains" for texts, "is" otherwise */
function defaultOperator(
    definition: AttributeDefinitionOrBuiltIn
): AQLOperator {
    const operators = getOperatorsForType(definition.type);

    return operators.includes(AQLOperator.CONTAINS)
        ? AQLOperator.CONTAINS
        : operators[0];
}

function isBlank(condition: AQLCondition): boolean {
    const right = condition.rightOperand;
    if (getOperatorArity(condition.operator) === 0) {
        return false;
    }
    const values =
        right === undefined ? [] : Array.isArray(right) ? right : [right];

    return values.every(v => isLiteral(v) && v.literal.trim() === '');
}

/** Any other type: the operator and value inputs of the condition builder */
function ConditionFilter({filter, ast, onApply}: WidgetProps) {
    const definitions = useMemo(
        () => ({[filter.field]: filter.definition}),
        [filter.field, filter.definition]
    );
    const [condition, setCondition] = useState<AQLCondition>(() => {
        const current = conditionsOn(filter.field, ast);
        if (current.length === 1) {
            return current[0];
        }
        const operator = defaultOperator(filter.definition);

        return {
            leftOperand: {field: filter.field},
            operator,
            rightOperand: fitValues([], operator, filter.definition),
        };
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        onApply(isBlank(condition) ? undefined : conditionToString(condition));
    };

    return (
        <form className="w-[min(34rem,90vw)] space-y-2 p-2" onSubmit={submit}>
            <ConditionRow
                condition={condition}
                definitions={definitions}
                onChange={setCondition}
                fixedField
            />
            <FilterActions
                canClear={!!ast}
                onClear={() => onApply(undefined)}
            />
        </form>
    );
}

function FilterActions({
    canClear,
    onClear,
}: {
    canClear: boolean;
    onClear: () => void;
}) {
    const {t} = useTranslation();

    return (
        <div className="flex justify-end gap-2">
            {canClear ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClear}
                >
                    {t('common.clear', 'Clear')}
                </Button>
            ) : null}
            <Button type="submit" size="sm" data-testid="pinned-filter-apply">
                {t('common.apply', 'Apply')}
            </Button>
        </div>
    );
}
