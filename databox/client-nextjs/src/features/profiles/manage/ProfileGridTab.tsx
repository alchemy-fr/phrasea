'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {
    CheckSquareIcon,
    ImageIcon,
    MoreVerticalIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
    type Active,
    type DragEndEvent,
    type DragMoveEvent,
    type DragStartEvent,
    type Over,
} from '@dnd-kit/core';
import {
    arrayMove,
    rectSortingStrategy,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
} from '@dnd-kit/sortable';
import {CSS, getEventCoordinates} from '@dnd-kit/utilities';
import type {
    AttributeDefinitionOrBuiltIn,
    GridAnchor,
    GridRegion,
    ProfileItem,
} from '@/types/api';
import {
    ProfileItemSection,
    ProfileItemSize,
    ProfileItemVariant,
} from '@/types/api';
import {Button} from '@/components/ui/button';
import {SimpleSelect} from '@/components/ui/select';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {getAttributeType} from '@/features/attributes/types/registry';
import {chipColors} from '@/features/assets/list/layouts/GridCardZones';
import {cn} from '@/lib/utils/cn';
import type {ProfileTabProps} from './ProfileManageRoute';
import {
    AttributePalette,
    attributeTypeOf,
    ProfileDragOverlay,
    paletteKeyOfItem,
    usePaletteGroups,
    useProfileDefinitions,
    type PaletteEntry,
} from './AttributePalette';
import {
    PALETTE_ZONE,
    paletteKeyOf,
    zoneCollision,
    type ZoneData,
    type ZoneItemData,
} from './profileDnd';
import {useProfileEditing} from './useProfileEditing';

type Cell = {region: GridRegion; anchor: GridAnchor};

// The top corners and the bottom-right cell of the thumbnail are taken by
// the card controls (selection, menu) and the file type
const overCells: {anchor: GridAnchor; cls: string}[] = [
    {anchor: 'tc', cls: 'col-start-2 row-start-1'},
    {anchor: 'ml', cls: 'col-start-1 row-start-2'},
    {anchor: 'cc', cls: 'col-start-2 row-start-2'},
    {anchor: 'mr', cls: 'col-start-3 row-start-2'},
    {anchor: 'bl', cls: 'col-start-1 row-start-3'},
    {anchor: 'bc', cls: 'col-start-2 row-start-3'},
];
const belowCells: GridAnchor[] = ['l', 'c', 'r'];

const CELL_PREFIX = 'cell:';
const cellId = ({region, anchor}: Cell) => `${CELL_PREFIX}${region}:${anchor}`;

function parseCell(id: string): Cell | undefined {
    if (!id.startsWith(CELL_PREFIX)) {
        return undefined;
    }
    const [region, anchor] = id.slice(CELL_PREFIX.length).split(':');

    return {region: region as GridRegion, anchor: anchor as GridAnchor};
}

/** The cell a drop lands in: the cell itself, or the cell of an item */
function cellOf(over: Over | null): Cell | undefined {
    if (!over) {
        return undefined;
    }
    const data = over.data.current as ZoneItemData | ZoneData | undefined;

    return parseCell(data?.kind === 'item' ? data.zone : String(over.id));
}

function cellLabel(t: TFunction, {region, anchor}: Cell): string {
    const anchors: Record<GridAnchor, string> = {
        tc: t('profile.grid.anchor.top', 'top'),
        ml: t('profile.grid.anchor.left', 'left'),
        cc: t('profile.grid.anchor.center', 'center'),
        mr: t('profile.grid.anchor.right', 'right'),
        bl: t('profile.grid.anchor.bottom_left', 'bottom left'),
        bc: t('profile.grid.anchor.bottom', 'bottom'),
        l: t('profile.grid.anchor.left', 'left'),
        c: t('profile.grid.anchor.center', 'center'),
        r: t('profile.grid.anchor.right', 'right'),
    };

    return region === 'over'
        ? t('profile.grid.zone.over', 'Over the thumbnail, {{anchor}}', {
              anchor: anchors[anchor],
          })
        : t('profile.grid.zone.below', 'Below the thumbnail, {{anchor}}', {
              anchor: anchors[anchor],
          });
}

/**
 * Editor of the grid card: which attribute values are shown over the
 * thumbnail (3×3 anchors) or in the band below it, with per-item rendering
 * options. Attributes are dragged from the palette onto a zone, moved
 * between zones or within one, and dragged back to the palette to remove
 * them; the palette button adds to the selected zone.
 */
export function ProfileGridTab({profile}: ProfileTabProps) {
    const {t} = useTranslation();
    const editing = useProfileEditing(profile.id);
    const {definitionOf, labelOf} = useProfileDefinitions();
    const [selected, setSelected] = useState<string | null>(null);
    const [target, setTarget] = useState<Cell>({region: 'below', anchor: 'l'});
    const [query, setQuery] = useState('');
    const [dragging, setDragging] = useState<string | null>(null);
    const [overCell, setOverCell] = useState<string | null>(null);
    const pointerX = useRef(0);
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
            // Enter selects a placed item
            keyboardCodes: {
                start: ['Space'],
                cancel: ['Escape'],
                end: ['Space', 'Enter'],
            },
        })
    );

    const items = useMemo(
        () =>
            (profile.items ?? []).filter(
                i => i.section === ProfileItemSection.Grid && i.placement
            ),
        [profile.items]
    );
    const used = useMemo(() => new Set(items.map(paletteKeyOfItem)), [items]);
    const groups = usePaletteGroups({used, query});

    const at = ({region, anchor}: Cell) =>
        items
            .filter(
                i =>
                    i.placement!.region === region &&
                    i.placement!.anchor === anchor
            )
            .sort(
                (a, b) => (a.placement!.order ?? 0) - (b.placement!.order ?? 0)
            );

    /** Saves the order of `list` in `cell`, for the items it changes */
    const persistOrder = (list: ProfileItem[], cell: Cell) => {
        const patches = list
            .map((item, order) => ({item, order}))
            .filter(
                ({item, order}) =>
                    item.placement?.region !== cell.region ||
                    item.placement?.anchor !== cell.anchor ||
                    item.placement?.order !== order
            )
            .map(({item, order}) => ({
                id: item.id,
                data: {placement: {...cell, order}},
            }));
        if (patches.length > 0) {
            void editing.update(patches);
        }
    };

    const addTo = async (entry: PaletteEntry, cell: Cell, index?: number) => {
        const list = at(cell);
        const position = index ?? list.length;
        const [added] = await editing.add([
            {
                ...entry.item,
                section: ProfileItemSection.Grid,
                placement: {...cell, order: position},
            },
        ]);
        if (added) {
            setSelected(added.id);
            if (position < list.length) {
                list.splice(position, 0, added);
                persistOrder(list, cell);
            }
        }
    };

    /** Index of the drop in the cell's list: before or after the item hovered */
    const dropIndex = (list: ProfileItem[], over: Over, active: Active) => {
        if (over.data.current?.kind !== 'item') {
            return list.filter(i => i.id !== active.id).length;
        }
        const index = list.findIndex(i => i.id === over.id);
        const middle = over.rect.left + over.rect.width / 2;

        return pointerX.current > middle ? index + 1 : index;
    };

    const onDragStart = ({active}: DragStartEvent) => {
        const entry = active.data.current?.entry as PaletteEntry | undefined;
        const item = items.find(i => i.id === active.id);
        setDragging(entry?.label ?? (item ? labelOf(item) : ''));
    };
    const onDragMove = ({over, activatorEvent, delta}: DragMoveEvent) => {
        pointerX.current =
            (getEventCoordinates(activatorEvent)?.x ?? 0) + delta.x;
        const cell = cellOf(over);
        const id = cell ? cellId(cell) : null;
        if (id !== overCell) {
            setOverCell(id);
        }
    };
    const reset = () => {
        setDragging(null);
        setOverCell(null);
    };
    const onDragEnd = ({active, over}: DragEndEvent) => {
        reset();
        if (!over) {
            return;
        }
        const cell = cellOf(over);

        if (paletteKeyOf(active.id)) {
            if (cell) {
                const entry = active.data.current?.entry as PaletteEntry;
                setTarget(cell);
                void addTo(entry, cell, dropIndex(at(cell), over, active));
            }

            return;
        }

        if (over.id === PALETTE_ZONE) {
            if (selected === active.id) {
                setSelected(null);
            }
            void editing.remove([String(active.id)]);

            return;
        }
        const item = items.find(i => i.id === active.id);
        if (!cell || !item?.placement) {
            return;
        }
        const list = at(cell);
        const sameCell =
            item.placement.region === cell.region &&
            item.placement.anchor === cell.anchor;
        if (sameCell) {
            // arrayMove keeps the direction of the drag: dropped over the
            // next item, it lands after it
            const from = list.findIndex(i => i.id === item.id);
            const to =
                over.data.current?.kind === 'item'
                    ? list.findIndex(i => i.id === over.id)
                    : list.length - 1;
            if (from !== to && to >= 0) {
                persistOrder(arrayMove(list, from, to), cell);
            }

            return;
        }
        const index = dropIndex(list, over, active);
        const moved = list.filter(i => i.id !== item.id);
        moved.splice(Math.min(index, moved.length), 0, item);
        persistOrder(moved, cell);
    };

    const selectedItem = items.find(i => i.id === selected);
    const renderCell = (cell: Cell, className?: string) => {
        const id = cellId(cell);

        return (
            <Zone
                key={id}
                id={id}
                className={className}
                items={at(cell)}
                isTarget={
                    target.region === cell.region &&
                    target.anchor === cell.anchor
                }
                isOver={overCell === id}
                dragging={!!dragging}
                selected={selected}
                labelOf={labelOf}
                label={cellLabel(t, cell)}
                onTarget={() => setTarget(cell)}
                onSelect={itemId => {
                    setSelected(itemId);
                    setTarget(cell);
                }}
            />
        );
    };

    return (
        <div className="flex h-full min-h-0 flex-col gap-3">
            <p className="text-sm text-muted-foreground">
                {t(
                    'profile.grid.intro',
                    'The attribute values shown on each thumbnail of the grid, over the image or in the band below it. Drag attributes onto a zone, move them between zones, drag them back to the list to remove them.'
                )}
            </p>
            <DndContext
                sensors={sensors}
                collisionDetection={zoneCollision}
                onDragStart={onDragStart}
                onDragMove={onDragMove}
                onDragOver={onDragMove}
                onDragEnd={onDragEnd}
                onDragCancel={reset}
            >
                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(14rem,1fr)_auto_minmax(15rem,1fr)] lg:grid-rows-[minmax(0,1fr)]">
                    <AttributePalette
                        groups={groups}
                        query={query}
                        onQueryChange={setQuery}
                        onAdd={e => void addTo(e, target)}
                        addIcon={<PlusIcon />}
                        addLabel={t(
                            'profile.grid.add_to_zone',
                            'Add to the selected zone'
                        )}
                        hint={t(
                            'profile.grid.add_hint',
                            '+ adds to: {{zone}}',
                            {
                                zone: cellLabel(t, target),
                            }
                        )}
                    />
                    <section className="flex flex-col items-center gap-2">
                        <h4 className="self-stretch text-xs font-semibold text-muted-foreground uppercase">
                            {t('profile.grid.card', 'Card layout')}
                        </h4>
                        <div
                            data-testid="profile-grid-card"
                            className="w-72 overflow-hidden rounded-lg border bg-card shadow-sm"
                        >
                            <div className="relative grid aspect-square grid-cols-3 grid-rows-3 gap-1 bg-media-bg p-1.5">
                                <ImageIcon className="pointer-events-none absolute top-1/2 left-1/2 size-16 -translate-x-1/2 -translate-y-1/2 text-muted-foreground/15" />
                                <ReservedCell className="col-start-1 row-start-1">
                                    <CheckSquareIcon />
                                </ReservedCell>
                                <ReservedCell className="col-start-3 row-start-1">
                                    <MoreVerticalIcon />
                                </ReservedCell>
                                <ReservedCell className="col-start-3 row-start-3">
                                    JPG
                                </ReservedCell>
                                {overCells.map(c =>
                                    renderCell(
                                        {region: 'over', anchor: c.anchor},
                                        c.cls
                                    )
                                )}
                            </div>
                            <div className="grid grid-cols-3 gap-1 p-1.5">
                                {belowCells.map(anchor =>
                                    renderCell({region: 'below', anchor})
                                )}
                            </div>
                        </div>
                    </section>
                    <section className="min-h-0 overflow-y-auto rounded-lg border p-3 text-sm">
                        {selectedItem ? (
                            <GridItemOptions
                                key={selectedItem.id}
                                item={selectedItem}
                                label={labelOf(selectedItem)}
                                definition={definitionOf(selectedItem)}
                                onChange={data =>
                                    void editing.update([
                                        {id: selectedItem.id, data},
                                    ])
                                }
                                onRemove={() => {
                                    setSelected(null);
                                    void editing.remove([selectedItem.id]);
                                }}
                            />
                        ) : (
                            <p className="text-muted-foreground">
                                {t(
                                    'profile.grid.select_item',
                                    'Select an attribute on the card to edit its rendering.'
                                )}
                            </p>
                        )}
                    </section>
                </div>
                <ProfileDragOverlay label={dragging} />
            </DndContext>
        </div>
    );
}

function ReservedCell({
    className,
    children,
}: {
    className: string;
    children: React.ReactNode;
}) {
    return (
        <div
            aria-hidden
            className={cn(
                'flex items-center justify-center text-[10px] font-medium text-muted-foreground/60 [&_svg]:size-3.5',
                className
            )}
        >
            {children}
        </div>
    );
}

function Zone({
    id,
    className,
    items,
    isTarget,
    isOver,
    dragging,
    selected,
    labelOf,
    label,
    onTarget,
    onSelect,
}: {
    id: string;
    className?: string;
    items: ProfileItem[];
    isTarget: boolean;
    isOver: boolean;
    dragging: boolean;
    selected: string | null;
    labelOf: (item: ProfileItem) => string;
    label: string;
    onTarget: () => void;
    onSelect: (id: string) => void;
}) {
    const {setNodeRef} = useDroppable({
        id,
        data: {kind: 'zone'} satisfies ZoneData,
    });

    return (
        <div
            ref={setNodeRef}
            role="button"
            tabIndex={0}
            data-testid="profile-grid-zone"
            data-zone={id}
            aria-label={label}
            title={label}
            onClick={onTarget}
            onKeyDown={e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onTarget();
                }
            }}
            className={cn(
                'relative flex min-h-9 flex-wrap content-center items-center justify-center gap-1 rounded-md border border-dashed p-1 text-[11px] transition-colors focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                dragging
                    ? 'border-primary/40 bg-background/40'
                    : 'border-foreground/15 hover:bg-background/40',
                isTarget && !dragging && 'border-primary/70 bg-primary/5',
                isOver && 'border-solid border-primary bg-primary/15',
                className
            )}
        >
            {items.length === 0 ? (
                <PlusIcon
                    className={cn(
                        'size-3 text-muted-foreground/40',
                        (isTarget || isOver) && 'text-primary'
                    )}
                />
            ) : null}
            <SortableContext
                id={id}
                items={items.map(i => i.id)}
                strategy={rectSortingStrategy}
            >
                {items.map(i => (
                    <ZoneChip
                        key={i.id}
                        item={i}
                        zone={id}
                        label={labelOf(i)}
                        selected={selected === i.id}
                        onSelect={() => onSelect(i.id)}
                    />
                ))}
            </SortableContext>
        </div>
    );
}

function ZoneChip({
    item,
    zone,
    label,
    selected,
    onSelect,
}: {
    item: ProfileItem;
    zone: string;
    label: string;
    selected: boolean;
    onSelect: () => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: item.id,
        data: {kind: 'item', zone} satisfies ZoneItemData,
    });
    const chip = item.variant === ProfileItemVariant.Chip;

    return (
        <span
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            data-testid="profile-grid-item"
            style={{transform: CSS.Translate.toString(transform), transition}}
            onClick={e => {
                e.stopPropagation();
                onSelect();
            }}
            onKeyDown={e => {
                // Space drags (see the keyboard sensor), Enter selects
                listeners?.onKeyDown?.(e);
                if (e.key === 'Enter') {
                    e.stopPropagation();
                    onSelect();
                }
            }}
            className={cn(
                'max-w-full cursor-grab touch-none truncate rounded px-1.5 py-0.5 shadow-sm select-none focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none active:cursor-grabbing',
                chip
                    ? cn(
                          'rounded-full',
                          (item.color && chipColors[item.color]) ||
                              'bg-secondary text-secondary-foreground'
                      )
                    : 'bg-background',
                selected && 'ring-2 ring-primary',
                isDragging && 'opacity-40'
            )}
        >
            {label}
        </span>
    );
}

function GridItemOptions({
    item,
    label,
    definition,
    onChange,
    onRemove,
}: {
    item: ProfileItem;
    label: string;
    definition?: AttributeDefinitionOrBuiltIn;
    onChange: (d: Partial<ProfileItem>) => void;
    onRemove: () => void;
}) {
    const {t} = useTranslation();
    const type = attributeTypeOf(definition);
    const typeDef = type ? getAttributeType(type) : undefined;
    const formats = typeDef?.formats(t) ?? [];

    return (
        <div className="space-y-3">
            <h4 className="font-semibold">{label}</h4>
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
                    value={item.size || ProfileItemSize.Medium}
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
                            aria-label={t('common.default', 'Default')}
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
