'use client';

import {Fragment, ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {
    MoreVerticalIcon,
    PencilIcon,
    PowerIcon,
    Trash2Icon,
} from 'lucide-react';
import type {AQLQuery} from '@/types/api';
import {useSearch} from '../SearchProvider';
import {parseAQL} from '../aql/parser';
import {operatorLabels, valueToString} from '../aql/serializer';
import {
    AQLCondition,
    AQLExpression,
    AQLLogical,
    AQLValueExpr,
    isEntity,
    isField,
    isLiteral,
    isLogical,
} from '../aql/types';
import {useDefinitionsBySlug} from '@/features/attributes/definitionsStore';
import {useEntitiesStore, ResolveStatus} from '../entitiesStore';
import {getAttributeType} from '@/features/attributes/types/registry';
import {useFormatContext} from '@/features/attributes/AttributeValue';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {ConditionDialog} from './ConditionDialog';
import {cn} from '@/lib/utils/cn';
import {iri} from '@/lib/utils/iri';
import {Tooltip} from '@/components/ui/overlays';

export function SearchConditionChip({condition}: {condition: AQLQuery}) {
    const {t} = useTranslation();
    const search = useSearch();
    const {openModal} = useModals();
    const ast = useMemo(() => parseAQL(condition.query), [condition.query]);

    const toggle = () =>
        search.upsertCondition({
            ...condition,
            disabled: condition.disabled ? undefined : true,
        });
    const edit = () => openModal(ConditionDialog, {condition, search});
    const remove = () => search.removeCondition(condition.id);

    const menuItems = (Item: typeof DropdownMenuItem) => (
        <>
            <Item onSelect={toggle}>
                <PowerIcon />{' '}
                {condition.disabled
                    ? t('search.condition.enable', 'Enable')
                    : t('search.condition.disable', 'Disable')}
            </Item>
            <Item onSelect={edit}>
                <PencilIcon /> {t('common.edit', 'Edit')}
            </Item>
            <Item onSelect={remove} variant="destructive">
                <Trash2Icon /> {t('common.remove', 'Remove')}
            </Item>
        </>
    );

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>
                <div
                    className={cn(
                        'group/chip inline-flex h-7 max-w-full items-center gap-1 rounded-full border pr-1 pl-2.5 text-xs transition-colors',
                        condition.disabled
                            ? 'border-warning/60 bg-warning/10 text-muted-foreground line-through decoration-warning'
                            : 'border-primary/30 bg-primary/10 text-foreground hover:bg-primary/15'
                    )}
                >
                    <Tooltip content={condition.query} delayDuration={600}>
                        <button
                            type="button"
                            className="min-w-0 truncate"
                            onClick={edit}
                        >
                            {ast ? (
                                <HumanizedExpression
                                    expression={ast.expression}
                                />
                            ) : (
                                condition.query
                            )}
                        </button>
                    </Tooltip>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="rounded-full p-0.5 text-muted-foreground opacity-60 hover:bg-foreground/10 hover:opacity-100"
                                aria-label={t('common.more', 'More')}
                            >
                                <MoreVerticalIcon className="size-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {menuItems(DropdownMenuItem)}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </ContextMenuTrigger>
            <ContextMenuContent>
                {menuItems(ContextMenuItem as any)}
            </ContextMenuContent>
        </ContextMenu>
    );
}

/**
 * Renders an AQL expression with display names, formatted values and
 * resolved entities (`@collection = "id"` → collection name).
 */
export function HumanizedExpression({
    expression,
}: {
    expression: AQLExpression;
}): ReactNode {
    if (isLogical(expression)) {
        if (expression.operator === AQLLogical.NOT) {
            return (
                <>
                    <Keyword>NOT</Keyword>{' '}
                    <HumanizedExpression
                        expression={expression.conditions[0]}
                    />
                </>
            );
        }

        return (
            <>
                {expression.conditions.map((c, i) => (
                    <Fragment key={i}>
                        {i > 0 ? (
                            <>
                                {' '}
                                <Keyword>{expression.operator}</Keyword>{' '}
                            </>
                        ) : null}
                        {isLogical(c) ? (
                            <>
                                (<HumanizedExpression expression={c} />)
                            </>
                        ) : (
                            <HumanizedExpression expression={c} />
                        )}
                    </Fragment>
                ))}
            </>
        );
    }

    return <HumanizedCondition condition={expression} />;
}

function Keyword({children}: {children: ReactNode}) {
    return (
        <span className="font-semibold text-muted-foreground">{children}</span>
    );
}

function HumanizedCondition({condition}: {condition: AQLCondition}) {
    const {t} = useTranslation();
    const definitions = useDefinitionsBySlug();
    const field = isField(condition.leftOperand)
        ? condition.leftOperand.field
        : undefined;
    const definition = field ? definitions[field] : undefined;
    const label =
        definition?.displayName ??
        definition?.name ??
        field ??
        valueToString(condition.leftOperand);
    const right = condition.rightOperand;
    const values =
        right === undefined ? [] : Array.isArray(right) ? right : [right];
    const isBetween = condition.operator.includes('BETWEEN');

    return (
        <>
            <span className="font-medium">{label}</span>{' '}
            <Keyword>{operatorLabels[condition.operator]}</Keyword>{' '}
            {values.length > 1 && !isBetween ? '(' : ''}
            {values.map((v, i) => (
                <Fragment key={i}>
                    {i > 0 ? isBetween ? <Keyword> AND </Keyword> : ', ' : null}
                    <HumanizedValue value={v} definition={definition} t={t} />
                </Fragment>
            ))}
            {values.length > 1 && !isBetween ? ')' : ''}
        </>
    );
}

function HumanizedValue({
    value,
    definition,
    t,
}: {
    value: AQLValueExpr;
    definition: ReturnType<typeof useDefinitionsBySlug>[string] | undefined;
    t: ReturnType<typeof useTranslation>['t'];
}) {
    const ctx = useFormatContext();
    const request = useEntitiesStore(s => s.request);
    const typeDef = definition ? getAttributeType(definition.type) : undefined;
    const entityName = typeDef?.entity;
    const literal = isLiteral(value) ? value.literal : undefined;
    const entityIri =
        entityName && literal ? iri(entityName, literal) : undefined;
    const resolved = useEntitiesStore(s =>
        entityIri ? s.index[entityIri] : undefined
    );
    if (entityIri && resolved === undefined) {
        request(entityIri);
    }

    if (value === true) return <>{t('aql.constant.yes', 'Yes')}</>;
    if (value === false) return <>{t('aql.constant.no', 'No')}</>;
    if (value === null) return <>{t('aql.constant.null', 'Null')}</>;
    if (isEntity(value)) {
        return <EntityPill label={value.label} />;
    }
    if (entityIri) {
        if (resolved === undefined) {
            return <span className="text-muted-foreground">…</span>;
        }
        if (resolved === ResolveStatus.NotFound) {
            return (
                <EntityPill label={t('entity.not_found', 'Not found')} muted />
            );
        }
        if (resolved === ResolveStatus.NotAllowed) {
            return (
                <EntityPill
                    label={t('entity.not_allowed', 'Not allowed')}
                    muted
                />
            );
        }

        return (
            <EntityPill
                label={
                    typeDef!.formatString(resolved, undefined, ctx) || literal!
                }
            />
        );
    }
    if (isLiteral(value) && typeDef) {
        return (
            <>
                {typeDef.formatString(value.literal, undefined, ctx) ||
                    `"${value.literal}"`}
            </>
        );
    }
    if (typeof value === 'number' && typeDef) {
        return (
            <>{typeDef.formatString(value, undefined, ctx) || String(value)}</>
        );
    }

    return <>{valueToString(value)}</>;
}

function EntityPill({label, muted}: {label: string; muted?: boolean}) {
    return (
        <span
            className={cn(
                'inline-flex max-w-48 items-center truncate rounded-sm bg-background/70 px-1 align-baseline font-medium',
                muted && 'text-muted-foreground italic'
            )}
        >
            {label}
        </span>
    );
}
