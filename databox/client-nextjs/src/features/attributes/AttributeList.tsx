'use client';

import {Fragment, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {EyeIcon, PinIcon, PinOffIcon} from 'lucide-react';
import type {Asset, AttributeDefinitionOrBuiltIn} from '@/types/api';
import {ProfileItemSection, ProfileItemType} from '@/types/api';
import {
    AttributeValue,
    formatValueForCopy,
    groupAttributes,
    useFormatContext,
} from './AttributeValue';
import {getAttributeType} from './types/registry';
import {useAttributeFormats} from './formatStore';
import {
    builtInTypes,
    builtInValueResolvers,
    useDefinitionsById,
} from './definitionsStore';
import {BuiltInAttribute} from '@/features/search/searchState';
import {CopyButton} from '@/components/ui/copy-button';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {Separator} from '@/components/ui/misc';
import {useProfileStore} from '@/features/profiles/profileStore';
import {cn} from '@/lib/utils/cn';
import {AttributeType} from '@/types/api';

type Item = {
    id: string;
    kind: 'definition' | 'builtin' | 'divider' | 'spacer';
    definition?: AttributeDefinitionOrBuiltIn;
    attribute?: ReturnType<typeof groupAttributes>[number]['attribute'];
    builtInValue?: unknown;
    format?: string;
};

type Props = {
    asset: Asset;
    /** show pin / copy / format controls */
    controls?: boolean;
    /** only show attributes pinned in the current display profile */
    pinnedOnly?: boolean;
    dense?: boolean;
    className?: string;
};

/**
 * Attribute list of an asset, ordered according to the current display
 * profile (pinned definitions, built-ins, dividers...), then the remaining
 * attributes.
 */
export function AttributeList({
    asset,
    controls = false,
    pinnedOnly = false,
    dense = false,
    className,
}: Props) {
    const {t} = useTranslation();
    const definitionsIndex = useDefinitionsById();
    const profile = useProfileStore(s => s.current);
    const toggleDefinition = useProfileStore(s => s.toggleDefinition);
    const pinnedItems = useMemo(
        () =>
            (profile?.items ?? []).filter(
                i => i.section === ProfileItemSection.Attributes
            ),
        [profile]
    );

    const items = useMemo<Item[]>(() => {
        const groups = groupAttributes(asset.attributes);
        if (pinnedItems.length === 0) {
            return pinnedOnly
                ? []
                : groups.map(g => ({
                      id: g.definition.id,
                      kind: 'definition',
                      definition: g.definition,
                      attribute: g.attribute,
                  }));
        }
        const out: Item[] = [];
        const used = new Set<string>();
        for (const item of pinnedItems) {
            if (item.type === ProfileItemType.Definition && item.definition) {
                const group = groups.find(
                    g => g.definition.id === item.definition
                );
                used.add(item.definition);
                if (group) {
                    out.push({
                        id: item.id,
                        kind: 'definition',
                        definition: group.definition,
                        attribute: group.attribute,
                        format: item.format,
                    });
                } else if (
                    item.displayEmpty &&
                    definitionsIndex[item.definition]
                ) {
                    out.push({
                        id: item.id,
                        kind: 'definition',
                        definition: definitionsIndex[item.definition],
                        format: item.format,
                    });
                }
            } else if (item.type === ProfileItemType.BuiltIn && item.key) {
                const resolver =
                    builtInValueResolvers[item.key as BuiltInAttribute];
                const def = definitionsIndex[item.key];
                const value = resolver?.(asset);
                const hasValue = Array.isArray(value)
                    ? value.length > 0
                    : value !== undefined && value !== null && value !== '';
                if (def && (hasValue || item.displayEmpty)) {
                    out.push({
                        id: item.id,
                        kind: 'builtin',
                        definition: def,
                        builtInValue: value,
                        format: item.format,
                    });
                }
            } else if (item.type === ProfileItemType.Divider) {
                out.push({id: item.id, kind: 'divider'});
            } else if (item.type === ProfileItemType.Spacer) {
                out.push({id: item.id, kind: 'spacer'});
            }
        }
        if (!pinnedOnly && !profile?.exclusive) {
            groups
                .filter(g => !used.has(g.definition.id))
                .forEach(g =>
                    out.push({
                        id: g.definition.id,
                        kind: 'definition',
                        definition: g.definition,
                        attribute: g.attribute,
                    })
                );
        }

        return out;
    }, [asset, pinnedItems, pinnedOnly, definitionsIndex, profile?.exclusive]);

    if (items.length === 0) {
        return (
            <p className="py-4 text-center text-sm text-muted-foreground">
                {t('attribute.list.empty', 'No attribute')}
            </p>
        );
    }

    return (
        <dl
            className={cn(
                'flex flex-col',
                dense ? 'gap-1.5' : 'gap-3',
                className
            )}
        >
            {items.map(item => {
                if (item.kind === 'divider') {
                    return <Separator key={item.id} className="my-1" />;
                }
                if (item.kind === 'spacer') {
                    return <div key={item.id} className="h-3" />;
                }
                const def = item.definition!;

                return (
                    <AttributeRow
                        key={item.id}
                        definition={def}
                        attribute={item.attribute}
                        builtInValue={item.builtInValue}
                        format={item.format}
                        controls={controls}
                        pinned={pinnedItems.some(
                            p =>
                                p.definition === def.id ||
                                p.key === def.searchSlug
                        )}
                        onTogglePin={() => toggleDefinition(def)}
                        dense={dense}
                    />
                );
            })}
        </dl>
    );
}

function AttributeRow({
    definition,
    attribute,
    builtInValue,
    format,
    controls,
    pinned,
    onTogglePin,
    dense,
}: {
    definition: AttributeDefinitionOrBuiltIn;
    attribute?: Item['attribute'];
    builtInValue?: unknown;
    format?: string;
    controls: boolean;
    pinned: boolean;
    onTogglePin: () => void;
    dense: boolean;
}) {
    const {t} = useTranslation();
    const ctx = useFormatContext();
    const {getFormat, setDefinitionFormat} = useAttributeFormats();
    const type = definition.builtIn
        ? (builtInTypes[definition.searchSlug as BuiltInAttribute] ??
          definition.type)
        : definition.type;
    const typeDef = getAttributeType(type);
    const formats = typeDef.formats(ctx.t);
    const current =
        format ?? getFormat(type, definition.id) ?? formats[0]?.name;

    const rotateFormat = () => {
        if (formats.length === 0) {
            return;
        }
        const idx = formats.findIndex(f => f.name === current);
        setDefinitionFormat(
            definition.id,
            formats[(idx + 1) % formats.length].name
        );
    };

    const copyValue = attribute
        ? formatValueForCopy(definition, attribute, ctx)
        : builtInValue !== undefined
          ? typeDef.formatString(builtInValue, undefined, ctx)
          : '';

    return (
        <div className="group/attr">
            <dt className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                <span className="truncate">
                    {definition.displayName ?? definition.name}
                </span>
                {controls ? (
                    <span className="ml-auto flex items-center opacity-0 transition-opacity group-hover/attr:opacity-100">
                        {formats.length > 1 ? (
                            <Tooltip
                                content={t(
                                    'attribute.change_format',
                                    'Change format ({{format}})',
                                    {
                                        format:
                                            formats.find(
                                                f => f.name === current
                                            )?.label ?? '',
                                    }
                                )}
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={rotateFormat}
                                >
                                    <EyeIcon />
                                </Button>
                            </Tooltip>
                        ) : null}
                        {copyValue ? <CopyButton value={copyValue} /> : null}
                        <Tooltip
                            content={
                                pinned
                                    ? t('attribute.unpin', 'Unpin from profile')
                                    : t('attribute.pin', 'Pin to profile')
                            }
                        >
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={onTogglePin}
                                className={cn(pinned && 'text-primary')}
                            >
                                {pinned ? <PinOffIcon /> : <PinIcon />}
                            </Button>
                        </Tooltip>
                    </span>
                ) : null}
            </dt>
            <dd className={cn('text-sm break-words', dense && 'text-xs')}>
                {attribute ? (
                    <AttributeValue
                        definition={definition}
                        attribute={attribute}
                        format={current}
                    />
                ) : builtInValue !== undefined && builtInValue !== null ? (
                    <BuiltInValue
                        type={type}
                        value={builtInValue}
                        format={current}
                    />
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </dd>
        </div>
    );
}

function BuiltInValue({
    type,
    value,
    format,
}: {
    type: AttributeType | string;
    value: unknown;
    format?: string;
}) {
    const ctx = useFormatContext();
    const typeDef = getAttributeType(type);
    const list = Array.isArray(value) ? value : [value];

    return (
        <div className={typeDef.rich ? 'flex flex-wrap gap-1' : undefined}>
            {list.map((v, i) => (
                <Fragment key={i}>{typeDef.format(v, format, ctx)}</Fragment>
            ))}
        </div>
    );
}
