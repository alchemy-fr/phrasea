'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {PlusIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {
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
import type {ProfileTabProps} from './ProfileManageRoute';
import {
    builtInTypes,
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {addToProfile, putProfileItem, removeFromProfile} from '@/lib/api/misc';
import {useProfileStore} from '../profileStore';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {getAttributeType} from '@/features/attributes/types/registry';
import {chipColors} from '@/features/assets/list/layouts/GridCardZones';
import type {BuiltInAttribute} from '@/features/search/searchState';
import {cn} from '@/lib/utils/cn';

const overAnchors: {anchor: GridAnchor; label: string; cls: string}[] = [
    {anchor: 'tc', label: 'Top', cls: 'col-start-2 row-start-1'},
    {anchor: 'ml', label: 'Left', cls: 'col-start-1 row-start-2'},
    {anchor: 'cc', label: 'Center', cls: 'col-start-2 row-start-2'},
    {anchor: 'mr', label: 'Right', cls: 'col-start-3 row-start-2'},
    {anchor: 'bl', label: 'Bottom left', cls: 'col-start-1 row-start-3'},
    {anchor: 'bc', label: 'Bottom', cls: 'col-start-2 row-start-3'},
];
const belowAnchors: {anchor: GridAnchor; label: string}[] = [
    {anchor: 'l', label: 'Left'},
    {anchor: 'c', label: 'Center'},
    {anchor: 'r', label: 'Right'},
];

/**
 * Editor of the grid card: which attribute values are shown over the
 * thumbnail (3×3 anchors) or in the band below it, with per-item rendering
 * options.
 */
export function GridProfileEditorTab({profile, refresh}: ProfileTabProps) {
    const {t} = useTranslation();
    const {definitions, builtIn, load} = useDefinitionsStore();
    const workspaces = useCollectionStore(s => s.workspaces);
    const loadWorkspaces = useCollectionStore(s => s.loadWorkspaces);
    const upsert = useProfileStore(s => s.upsert);
    const [selected, setSelected] = useState<string | null>(null);
    const [target, setTarget] = useState<{
        region: GridRegion;
        anchor: GridAnchor;
    }>({region: 'below', anchor: 'l'});
    const [query, setQuery] = useState('');

    useEffect(() => {
        void load();
        void loadWorkspaces();
    }, [load, loadWorkspaces]);

    const items = useMemo(
        () =>
            (profile.items ?? []).filter(
                i => i.section === ProfileItemSection.Grid && i.placement
            ),
        [profile.items]
    );
    const definitionOf = (
        item: ProfileItem
    ): AttributeDefinitionOrBuiltIn | undefined =>
        item.type === ProfileItemType.BuiltIn
            ? builtIn.find(x => x.searchSlug === item.key)
            : definitions.find(x => x.id === item.definition);
    const labelOf = (item: ProfileItem) => {
        const d = definitionOf(item);

        return d?.displayName ?? d?.name ?? item.key ?? item.definition ?? '';
    };

    const palette = useMemo(() => {
        const q = query.toLowerCase();
        const match = (d: AttributeDefinitionOrBuiltIn) =>
            !q || (d.displayName ?? d.name).toLowerCase().includes(q);

        return [
            ...builtIn
                .filter(match)
                .map(b => ({
                    key: b.searchSlug,
                    label: b.displayName ?? b.name,
                    item: {
                        type: ProfileItemType.BuiltIn,
                        key: b.searchSlug,
                    } as Partial<ProfileItem>,
                })),
            ...definitions.filter(match).map(d => ({
                key: d.id,
                label: `${d.displayName ?? d.name} · ${workspaces.find(w => w.id === workspaceIdOf(d))?.displayName ?? ''}`,
                item: {
                    type: ProfileItemType.Definition,
                    definition: d.id,
                } as Partial<ProfileItem>,
            })),
        ];
    }, [builtIn, definitions, workspaces, query]);

    const add = async (item: Partial<ProfileItem>) => {
        try {
            const updated = await addToProfile(profile.id, [
                {
                    ...item,
                    id: '',
                    section: ProfileItemSection.Grid,
                    placement: {
                        region: target.region,
                        anchor: target.anchor,
                        order: items.filter(
                            i => i.placement?.anchor === target.anchor
                        ).length,
                    },
                } as ProfileItem,
            ]);
            upsert(updated);
            refresh();
        } catch (e: any) {
            toast.error(e?.message);
        }
    };
    const remove = async (id: string) => {
        const updated = await removeFromProfile(profile.id, [id]);
        upsert(updated);
        refresh();
    };
    const update = async (id: string, data: Partial<ProfileItem>) => {
        await putProfileItem(profile.id, id, data);
        refresh();
    };

    const at = (region: GridRegion, anchor: GridAnchor) =>
        items
            .filter(
                i =>
                    i.placement!.region === region &&
                    i.placement!.anchor === anchor
            )
            .sort(
                (a, b) => (a.placement!.order ?? 0) - (b.placement!.order ?? 0)
            );
    const selectedItem = items.find(i => i.id === selected);

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(14rem,0.8fr)_1.3fr_minmax(14rem,0.9fr)]">
            <div className="space-y-2">
                <h4 className="text-xs font-semibold text-muted-foreground uppercase">
                    {t('profile.grid.palette', 'Attributes')}
                </h4>
                <Input
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t('common.search', 'Search…')}
                    className="h-8"
                />
                <p className="text-xs text-muted-foreground">
                    {t(
                        'profile.grid.palette_help',
                        'Select a zone on the card, then add attributes.'
                    )}
                </p>
                <ul className="max-h-[50vh] space-y-0.5 overflow-y-auto rounded-md border p-1">
                    {palette.map(p => (
                        <li
                            key={p.key}
                            className="flex items-center gap-1 rounded px-1 py-0.5 text-sm hover:bg-accent/60"
                        >
                            <span className="min-w-0 flex-1 truncate">
                                {p.label}
                            </span>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={() => add(p.item)}
                                aria-label="Add"
                            >
                                <PlusIcon />
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
            <div className="space-y-2">
                <h4 className="text-xs font-semibold text-muted-foreground uppercase">
                    {t('profile.grid.card', 'Card layout')}
                </h4>
                <div className="mx-auto w-64 overflow-hidden rounded-lg border bg-card shadow-sm">
                    <div className="relative grid aspect-square grid-cols-3 grid-rows-3 gap-1 bg-media-bg p-2">
                        <div className="col-start-1 row-start-1 flex items-center justify-center text-[10px] text-muted-foreground">
                            ☐
                        </div>
                        <div className="col-start-3 row-start-1 flex items-center justify-center text-[10px] text-muted-foreground">
                            ⋮
                        </div>
                        <div className="col-start-3 row-start-3 flex items-center justify-center text-[10px] text-muted-foreground">
                            JPG
                        </div>
                        {overAnchors.map(a => (
                            <Zone
                                key={a.anchor}
                                region="over"
                                anchor={a.anchor}
                                className={a.cls}
                                items={at('over', a.anchor)}
                                target={target}
                                selected={selected}
                                labelOf={labelOf}
                                onTarget={setTarget}
                                onSelect={setSelected}
                            />
                        ))}
                    </div>
                    <div className="space-y-1 p-2">
                        {belowAnchors.map(a => (
                            <Zone
                                key={a.anchor}
                                region="below"
                                anchor={a.anchor}
                                items={at('below', a.anchor)}
                                target={target}
                                selected={selected}
                                labelOf={labelOf}
                                onTarget={setTarget}
                                onSelect={setSelected}
                            />
                        ))}
                    </div>
                </div>
                <p className="text-center text-xs text-muted-foreground">
                    {t('profile.grid.target', 'Target zone')}:{' '}
                    <Badge variant="secondary">
                        {target.region} / {target.anchor}
                    </Badge>
                </p>
            </div>
            <div className="rounded-md border p-3 text-sm">
                {selectedItem ? (
                    <GridItemOptions
                        key={selectedItem.id}
                        item={selectedItem}
                        definition={definitionOf(selectedItem)}
                        onChange={data => update(selectedItem.id, data)}
                        onRemove={() => remove(selectedItem.id)}
                    />
                ) : (
                    <p className="text-muted-foreground">
                        {t(
                            'profile.grid.select_item',
                            'Select an attribute on the card to edit its rendering.'
                        )}
                    </p>
                )}
            </div>
        </div>
    );
}

function Zone({
    region,
    anchor,
    className,
    items,
    target,
    selected,
    labelOf,
    onTarget,
    onSelect,
}: {
    region: GridRegion;
    anchor: GridAnchor;
    className?: string;
    items: ProfileItem[];
    target: {region: GridRegion; anchor: GridAnchor};
    selected: string | null;
    labelOf: (item: ProfileItem) => string;
    onTarget: (t: {region: GridRegion; anchor: GridAnchor}) => void;
    onSelect: (id: string) => void;
}) {
    const isTarget = target.region === region && target.anchor === anchor;

    return (
        <button
            type="button"
            onClick={() => onTarget({region, anchor})}
            className={cn(
                'flex min-h-10 flex-wrap items-center justify-center gap-1 rounded border border-dashed p-1 text-[11px] transition-colors',
                isTarget
                    ? 'border-primary bg-primary/10'
                    : 'border-border/60 hover:bg-accent/40',
                className
            )}
        >
            {items.length === 0 ? (
                <PlusIcon className="size-3 opacity-40" />
            ) : null}
            {items.map(i => (
                <span
                    key={i.id}
                    role="button"
                    onClick={e => {
                        e.stopPropagation();
                        onSelect(i.id);
                    }}
                    className={cn(
                        'rounded bg-background px-1.5 py-0.5 shadow-sm',
                        selected === i.id && 'ring-2 ring-primary'
                    )}
                >
                    {labelOf(i)}
                </span>
            ))}
        </button>
    );
}

function GridItemOptions({
    item,
    definition,
    onChange,
    onRemove,
}: {
    item: ProfileItem;
    definition?: AttributeDefinitionOrBuiltIn;
    onChange: (d: Partial<ProfileItem>) => void;
    onRemove: () => void;
}) {
    const {t} = useTranslation();
    const type = definition?.builtIn
        ? (builtInTypes[definition.searchSlug as BuiltInAttribute] ??
          definition.type)
        : definition?.type;
    const typeDef = type ? getAttributeType(type) : undefined;
    const formats = typeDef?.formats(t) ?? [];

    return (
        <div className="space-y-3">
            <h4 className="font-semibold">
                {definition?.displayName ?? definition?.name}
            </h4>
            <div>
                <div className="mb-1 text-xs text-muted-foreground">
                    {t('profile.grid.variant', 'Display as')}
                </div>
                <SimpleSelect
                    value={
                        item.variant ??
                        (typeDef?.rich
                            ? ProfileItemVariant.Rich
                            : ProfileItemVariant.Text)
                    }
                    onValueChange={v =>
                        onChange({variant: v as ProfileItemVariant})
                    }
                    options={[
                        ...(typeDef?.rich
                            ? [
                                  {
                                      value: ProfileItemVariant.Rich,
                                      label: t('profile.grid.rich', 'Rich'),
                                  },
                              ]
                            : []),
                        {
                            value: ProfileItemVariant.Chip,
                            label: t('profile.grid.chip', 'Chip'),
                        },
                        {
                            value: ProfileItemVariant.Text,
                            label: t('profile.grid.text', 'Text'),
                        },
                    ]}
                />
            </div>
            {formats.length > 0 ? (
                <div>
                    <div className="mb-1 text-xs text-muted-foreground">
                        {t('profile.format', 'Format')}
                    </div>
                    <SimpleSelect
                        value={item.format || '__default'}
                        onValueChange={v =>
                            onChange({format: v === '__default' ? '' : v})
                        }
                        options={[
                            {
                                value: '__default',
                                label: t('common.default', 'Default'),
                            },
                            ...formats.map(f => ({
                                value: f.name,
                                label: f.label,
                            })),
                        ]}
                    />
                </div>
            ) : null}
            <div>
                <div className="mb-1 text-xs text-muted-foreground">
                    {t('profile.grid.size', 'Size')}
                </div>
                <SimpleSelect
                    value={item.size ?? ProfileItemSize.Medium}
                    onValueChange={v => onChange({size: v as ProfileItemSize})}
                    options={[
                        {value: ProfileItemSize.Small, label: 'S'},
                        {value: ProfileItemSize.Medium, label: 'M'},
                        {value: ProfileItemSize.Large, label: 'L'},
                    ]}
                />
            </div>
            {item.variant === ProfileItemVariant.Chip ? (
                <div>
                    <div className="mb-1 text-xs text-muted-foreground">
                        {t('profile.grid.color', 'Chip color')}
                    </div>
                    <div className="flex flex-wrap gap-1">
                        <button
                            type="button"
                            className={cn(
                                'size-6 rounded-full border bg-secondary',
                                !item.color && 'ring-2 ring-primary'
                            )}
                            onClick={() => onChange({color: ''})}
                            aria-label="default"
                        />
                        {Object.entries(chipColors).map(([name, cls]) => (
                            <button
                                key={name}
                                type="button"
                                className={cn(
                                    'size-6 rounded-full border',
                                    cls.split(' ')[0],
                                    item.color === name && 'ring-2 ring-primary'
                                )}
                                onClick={() => onChange({color: name})}
                                aria-label={name}
                            />
                        ))}
                    </div>
                </div>
            ) : null}
            <LabeledControl label={t('profile.grid.show_label', 'Show label')}>
                <Checkbox
                    checked={!!item.showLabel}
                    onCheckedChange={v => onChange({showLabel: v === true})}
                />
            </LabeledControl>
            <LabeledControl
                label={t('profile.show_if_empty', 'Show even if empty')}
            >
                <Checkbox
                    checked={!!item.displayEmpty}
                    onCheckedChange={v => onChange({displayEmpty: v === true})}
                />
            </LabeledControl>
            <div>
                <div className="mb-1 text-xs text-muted-foreground">
                    {t('profile.grid.order', 'Order in zone')}
                </div>
                <Input
                    type="number"
                    className="w-24"
                    value={item.placement?.order ?? 0}
                    onChange={e =>
                        onChange({
                            placement: {
                                ...item.placement!,
                                order: Number(e.target.value),
                            },
                        })
                    }
                />
            </div>
            <Button
                variant="ghost"
                size="sm"
                className="text-destructive"
                onClick={onRemove}
            >
                <Trash2Icon /> {t('common.remove', 'Remove')}
            </Button>
        </div>
    );
}
