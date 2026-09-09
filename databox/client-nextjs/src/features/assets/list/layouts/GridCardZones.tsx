'use client';

import {Fragment, ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import type {
    Asset,
    AttributeDefinitionOrBuiltIn,
    GridAnchor,
    GridRegion,
    ProfileItem,
} from '@/types/api';
import {
    ProfileItemSection,
    ProfileItemSize,
    ProfileItemType,
    ProfileItemVariant,
} from '@/types/api';
import {useProfileStore} from '@/features/profiles/profileStore';
import {
    builtInTypes,
    builtInValueResolvers,
    useDefinitionsById,
} from '@/features/attributes/definitionsStore';
import {groupAttributes} from '@/features/attributes/AttributeValue';
import {getAttributeType} from '@/features/attributes/types/registry';
import {useAttributeFormats} from '@/features/attributes/formatStore';
import type {BuiltInAttribute} from '@/features/search/searchState';
import {Chip} from '@/components/ui/misc';
import {cn} from '@/lib/utils/cn';

export const chipColors: Record<string, string> = {
    red: 'bg-red-500 text-white',
    orange: 'bg-orange-500 text-white',
    amber: 'bg-amber-400 text-black',
    yellow: 'bg-yellow-300 text-black',
    lime: 'bg-lime-500 text-black',
    green: 'bg-green-600 text-white',
    teal: 'bg-teal-600 text-white',
    cyan: 'bg-cyan-500 text-black',
    blue: 'bg-blue-600 text-white',
    indigo: 'bg-indigo-600 text-white',
    purple: 'bg-purple-600 text-white',
    pink: 'bg-pink-500 text-white',
};

export function useGridProfileItems(): ProfileItem[] {
    const profile = useProfileStore(s => s.current);

    return useMemo(
        () =>
            (profile?.items ?? []).filter(
                i => i.section === ProfileItemSection.Grid && i.placement
            ),
        [profile]
    );
}

type Resolved = {
    item: ProfileItem;
    definition: AttributeDefinitionOrBuiltIn;
    text: string;
    nodes: ReactNode[];
    rich: boolean;
};

const anchorClasses: Record<GridAnchor, string> = {
    tc: 'top-1 left-1/2 -translate-x-1/2',
    ml: 'top-1/2 left-1 -translate-y-1/2',
    cc: 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2',
    mr: 'top-1/2 right-1 -translate-y-1/2',
    bl: 'bottom-1 left-1',
    bc: 'bottom-1 left-1/2 -translate-x-1/2',
    l: 'justify-start',
    c: 'justify-center',
    r: 'justify-end',
};

const sizeClasses: Record<ProfileItemSize, string> = {
    [ProfileItemSize.Small]: 'text-[11px]',
    [ProfileItemSize.Medium]: 'text-xs',
    [ProfileItemSize.Large]: 'text-sm',
};

/**
 * Renders the attribute values configured in the display profile's grid
 * section, either over the thumbnail (3×3 anchors) or in a band below it.
 */
export function GridCardZones({
    asset,
    items,
    region,
    thumbSize,
}: {
    asset: Asset;
    items: ProfileItem[];
    region: GridRegion;
    thumbSize: number;
}) {
    const {t, i18n} = useTranslation();
    const definitions = useDefinitionsById();
    const {getFormat} = useAttributeFormats();

    const resolved = useMemo<Resolved[]>(() => {
        const groups = groupAttributes(asset.attributes);
        const ctx = {t, lang: i18n.language};
        const out: Resolved[] = [];
        for (const item of items.filter(i => i.placement?.region === region)) {
            let definition: AttributeDefinitionOrBuiltIn | undefined;
            let values: unknown[] = [];
            if (item.type === ProfileItemType.Definition && item.definition) {
                definition = definitions[item.definition];
                const group = groups.find(
                    g => g.definition.id === item.definition
                );
                if (group) {
                    values = (
                        Array.isArray(group.attribute)
                            ? group.attribute
                            : [group.attribute]
                    ).map(a => a.value);
                }
            } else if (item.type === ProfileItemType.BuiltIn && item.key) {
                definition = definitions[item.key];
                const v =
                    builtInValueResolvers[item.key as BuiltInAttribute]?.(
                        asset
                    );
                values = Array.isArray(v)
                    ? v
                    : v === undefined || v === null
                      ? []
                      : [v];
            }
            if (!definition) {
                continue;
            }
            const type = definition.builtIn
                ? (builtInTypes[definition.searchSlug as BuiltInAttribute] ??
                  definition.type)
                : definition.type;
            const typeDef = getAttributeType(type);
            const format = item.format || getFormat(type, definition.id);
            const text = values
                .map(v => typeDef.formatString(v, format, ctx))
                .filter(Boolean)
                .join(', ');
            if (!text && !item.displayEmpty) {
                continue;
            }
            out.push({
                item,
                definition,
                text,
                nodes: values.map(v => typeDef.format(v, format, ctx)),
                rich: !!typeDef.rich,
            });
        }
        out.sort(
            (a, b) =>
                (a.item.placement?.order ?? 0) - (b.item.placement?.order ?? 0)
        );

        return out;
    }, [asset, items, region, definitions, getFormat, t, i18n.language]);

    if (resolved.length === 0) {
        return null;
    }

    if (region === 'over') {
        const byAnchor = new Map<GridAnchor, Resolved[]>();
        resolved.forEach(r => {
            const a = r.item.placement!.anchor;
            byAnchor.set(a, [...(byAnchor.get(a) ?? []), r]);
        });

        return (
            <>
                {[...byAnchor.entries()].map(([anchor, list]) => (
                    <div
                        key={anchor}
                        className={cn(
                            'pointer-events-none absolute flex max-w-[90%] flex-col gap-0.5',
                            anchorClasses[anchor]
                        )}
                    >
                        {list.map(r => (
                            <ZoneValue key={r.item.id} resolved={r} overlay />
                        ))}
                    </div>
                ))}
            </>
        );
    }

    const byAnchor: Record<string, Resolved[]> = {l: [], c: [], r: []};
    resolved.forEach(r => {
        const a = r.item.placement!.anchor;
        (byAnchor[a] ?? byAnchor.l).push(r);
    });

    return (
        <div
            className="flex flex-col gap-1 p-2"
            style={{minHeight: Math.max(32, thumbSize * 0.15)}}
        >
            {(['l', 'c', 'r'] as const).map(a =>
                byAnchor[a].length > 0 ? (
                    <div
                        key={a}
                        className={cn(
                            'flex flex-wrap items-center gap-1',
                            anchorClasses[a]
                        )}
                    >
                        {byAnchor[a].map(r => (
                            <ZoneValue key={r.item.id} resolved={r} />
                        ))}
                    </div>
                ) : null
            )}
        </div>
    );
}

function ZoneValue({
    resolved,
    overlay,
}: {
    resolved: Resolved;
    overlay?: boolean;
}) {
    const {item, definition, text, nodes, rich} = resolved;
    const size = sizeClasses[item.size ?? ProfileItemSize.Medium];
    const label = item.showLabel
        ? `${definition.displayName ?? definition.name}: `
        : '';
    const variant =
        item.variant ??
        (rich ? ProfileItemVariant.Rich : ProfileItemVariant.Text);

    if (variant === ProfileItemVariant.Chip) {
        return (
            <Chip
                size="xs"
                className={cn(
                    size,
                    item.color && chipColors[item.color],
                    overlay && 'pointer-events-auto shadow-sm'
                )}
                title={text}
            >
                {label}
                {text || '—'}
            </Chip>
        );
    }
    if (variant === ProfileItemVariant.Rich && rich) {
        return (
            <span
                className={cn(
                    'inline-flex flex-wrap items-center gap-1',
                    size,
                    overlay && 'rounded bg-background/80 px-1 py-0.5 shadow-sm'
                )}
                title={text}
            >
                {label ? (
                    <span className="text-muted-foreground">{label}</span>
                ) : null}
                {nodes.map((n, i) => (
                    <Fragment key={i}>{n}</Fragment>
                ))}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'truncate',
                size,
                overlay && 'rounded bg-background/80 px-1 py-0.5 shadow-sm'
            )}
            title={text}
        >
            {label ? (
                <span className="text-muted-foreground">{label}</span>
            ) : null}
            {text || '—'}
        </span>
    );
}
