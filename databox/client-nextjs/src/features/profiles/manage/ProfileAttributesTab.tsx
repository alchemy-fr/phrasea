'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ArrowRightIcon,
    ChevronDownIcon,
    EyeOffIcon,
    GripVerticalIcon,
    XIcon,
} from 'lucide-react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragMoveEvent,
    type DragStartEvent,
    type Over,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import type {AttributeDefinitionOrBuiltIn, ProfileItem} from '@/types/api';
import {ProfileItemSection, ProfileItemType} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {Input} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {getAttributeType} from '@/features/attributes/types/registry';
import {cn} from '@/lib/utils/cn';
import type {ProfileTabProps} from './ProfileManageRoute';
import {
    AttributePalette,
    attributeTypeOf,
    ProfileDragOverlay,
    isLayoutItem,
    paletteKeyOfItem,
    usePaletteGroups,
    useProfileDefinitions,
    type PaletteEntry,
} from './AttributePalette';
import {
    PALETTE_ZONE,
    paletteKeyOf,
    useDragPosition,
    zoneCollision,
} from './profileDnd';
import type {ZoneData, ZoneItemData} from './profileDnd';
import {useProfileEditing} from './useProfileEditing';

const LIST_ZONE = 'displayed';

/**
 * The attributes of the asset panel: the palette of the attributes on the
 * left, the displayed ones on the right in their order. Attributes are
 * dragged from the palette to a position of the list, reordered within it,
 * and dragged back to the palette to remove them.
 */
export function ProfileAttributesTab({profile}: ProfileTabProps) {
    const {t} = useTranslation();
    const editing = useProfileEditing(profile.id);
    const {definitionOf, labelOf} = useProfileDefinitions();
    const [query, setQuery] = useState('');
    const [expanded, setExpanded] = useState<string | null>(null);
    const [dragging, setDragging] = useState<string | null>(null);
    // Where a palette entry dragged over the list would land
    const [insertion, setInsertion] = useState<number | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const items = useMemo(
        () =>
            (profile.items ?? []).filter(
                i => i.section === ProfileItemSection.Attributes
            ),
        [profile.items]
    );
    const ids = useMemo(() => items.map(i => i.id), [items]);
    const used = useMemo(
        () =>
            new Set(items.filter(i => !isLayoutItem(i)).map(paletteKeyOfItem)),
        [items]
    );
    const groups = usePaletteGroups({used, query, withLayout: true});

    const add = (entry: PaletteEntry, index?: number) =>
        editing.add(
            [{...entry.item, section: ProfileItemSection.Attributes}],
            index !== undefined
                ? {section: ProfileItemSection.Attributes, index}
                : undefined
        );

    // Where the drag is, to tell whether a palette entry lands above or
    // below the row it hovers
    const dragPosition = useDragPosition();
    const pointerY = useRef(0);
    const insertionAt = (over: Over | null): number | null => {
        if (over?.id === LIST_ZONE) {
            return items.length;
        }
        const index = over ? ids.indexOf(String(over.id)) : -1;
        if (!over || index < 0) {
            return null;
        }
        const middle = over.rect.top + over.rect.height / 2;

        return pointerY.current > middle ? index + 1 : index;
    };
    const onDragStart = ({active}: DragStartEvent) => {
        const entry = active.data.current?.entry as PaletteEntry | undefined;
        const item = items.find(i => i.id === active.id);
        setDragging(entry?.label ?? (item ? labelOf(item) : ''));
    };
    // `over` may lag one move behind: tracked on both events
    const onDragMove = ({active, over, activatorEvent}: DragMoveEvent) => {
        if (!paletteKeyOf(active.id)) {
            return;
        }
        pointerY.current = dragPosition(active, activatorEvent).y;
        const next = insertionAt(over);
        setInsertion(prev => (prev === next ? prev : next));
    };
    const reset = () => {
        setDragging(null);
        setInsertion(null);
    };
    const onDragEnd = ({active, over, activatorEvent}: DragEndEvent) => {
        reset();
        if (!over) {
            return;
        }
        if (paletteKeyOf(active.id)) {
            pointerY.current = dragPosition(active, activatorEvent).y;
            const at = insertionAt(over);
            if (at !== null) {
                void add(active.data.current?.entry as PaletteEntry, at);
            }

            return;
        }
        if (over.id === PALETTE_ZONE) {
            void editing.remove([String(active.id)]);

            return;
        }
        const from = ids.indexOf(String(active.id));
        const to =
            over.id === LIST_ZONE
                ? ids.length - 1
                : ids.indexOf(String(over.id));
        if (from < 0 || to < 0 || from === to) {
            return;
        }
        void editing.sort(arrayMove(ids, from, to));
    };

    return (
        <div className="flex h-full min-h-0 flex-col gap-3">
            <p className="text-sm text-muted-foreground">
                {t(
                    'profile.attributes.intro',
                    'The attributes shown in the asset panel, in this order. Drag attributes from the list on the left, reorder them, drag them back to remove them.'
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
                <div className="grid min-h-0 flex-1 gap-4 md:grid-cols-[minmax(15rem,2fr)_3fr] md:grid-rows-[minmax(0,1fr)]">
                    <AttributePalette
                        groups={groups}
                        query={query}
                        onQueryChange={setQuery}
                        onAdd={e => void add(e)}
                        addIcon={<ArrowRightIcon />}
                        addLabel={t('profile.palette.add', 'Add')}
                    />
                    <DisplayedList
                        count={items.length}
                        dragging={!!dragging}
                        droppingNew={insertion !== null}
                    >
                        <SortableContext
                            items={ids}
                            strategy={verticalListSortingStrategy}
                        >
                            {items.map((item, index) => (
                                <DisplayedRow
                                    key={item.id}
                                    item={item}
                                    label={labelOf(item)}
                                    definition={definitionOf(item)}
                                    insertBefore={insertion === index}
                                    insertAfter={
                                        index === items.length - 1 &&
                                        insertion === items.length
                                    }
                                    expanded={expanded === item.id}
                                    onToggle={() =>
                                        setExpanded(
                                            expanded === item.id
                                                ? null
                                                : item.id
                                        )
                                    }
                                    onRemove={() =>
                                        void editing.remove([item.id])
                                    }
                                    onChange={data =>
                                        void editing.update([
                                            {id: item.id, data},
                                        ])
                                    }
                                />
                            ))}
                        </SortableContext>
                    </DisplayedList>
                </div>
                <ProfileDragOverlay label={dragging} />
            </DndContext>
        </div>
    );
}

function DisplayedList({
    count,
    dragging,
    droppingNew,
    children,
}: {
    count: number;
    dragging: boolean;
    droppingNew: boolean;
    children: React.ReactNode;
}) {
    const {t} = useTranslation();
    const {setNodeRef} = useDroppable({
        id: LIST_ZONE,
        data: {kind: 'zone'} satisfies ZoneData,
    });

    return (
        <section className="flex min-h-0 flex-col gap-2">
            <h4 className="flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase">
                {t('profile.displayed', 'Displayed')}
                <Badge variant="muted">{count}</Badge>
            </h4>
            <div
                ref={setNodeRef}
                data-testid="profile-displayed"
                className={cn(
                    'min-h-0 flex-1 overflow-y-auto rounded-lg border p-2 transition-colors',
                    dragging && 'border-dashed',
                    droppingNew && 'border-primary bg-primary/5'
                )}
            >
                {count === 0 ? (
                    <div className="flex h-full min-h-40 flex-col items-center justify-center gap-1 rounded-md text-center text-sm text-muted-foreground">
                        <span>
                            {t(
                                'profile.attributes.empty',
                                'No attribute is pinned yet.'
                            )}
                        </span>
                        <span className="text-xs">
                            {t(
                                'profile.attributes.empty_help',
                                'Drag attributes here, or add them with the arrow.'
                            )}
                        </span>
                    </div>
                ) : (
                    <ul>{children}</ul>
                )}
            </div>
        </section>
    );
}

function DisplayedRow({
    item,
    label,
    definition,
    insertBefore,
    insertAfter,
    expanded,
    onToggle,
    onRemove,
    onChange,
}: {
    item: ProfileItem;
    label: string;
    definition?: AttributeDefinitionOrBuiltIn;
    insertBefore: boolean;
    insertAfter: boolean;
    expanded: boolean;
    onToggle: () => void;
    onRemove: () => void;
    onChange: (data: Partial<ProfileItem>) => void;
}) {
    const {t} = useTranslation();
    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: item.id,
        data: {kind: 'item', zone: LIST_ZONE} satisfies ZoneItemData,
    });
    const type = attributeTypeOf(definition);

    return (
        // Padded rather than spaced: the rows are contiguous drop targets
        <li
            ref={setNodeRef}
            data-testid="profile-displayed-item"
            style={{transform: CSS.Translate.toString(transform), transition}}
            className="relative py-0.5"
        >
            {insertBefore ? <InsertionMark position="top" /> : null}
            <div
                className={cn(
                    'rounded-md border bg-card text-sm',
                    expanded && 'border-primary/60',
                    isDragging && 'opacity-40'
                )}
            >
                {/* The whole row drags; the keyboard, from the handle */}
                <div
                    {...listeners}
                    className="flex cursor-grab touch-none items-center gap-1 pr-1 active:cursor-grabbing"
                >
                    <span
                        ref={setActivatorNodeRef}
                        {...attributes}
                        className="flex self-stretch rounded px-1 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                        aria-label={t(
                            'profile.drag_to_reorder',
                            'Drag to reorder'
                        )}
                    >
                        <GripVerticalIcon className="size-4 self-center" />
                    </span>
                    {item.type === ProfileItemType.Divider ? (
                        <button
                            type="button"
                            className="flex min-w-0 flex-1 items-center gap-2 py-2 text-xs text-muted-foreground"
                            onClick={onToggle}
                            aria-expanded={expanded}
                        >
                            <span className="h-px min-w-4 flex-1 bg-border" />
                            {item.key ? (
                                <span className="max-w-[70%] truncate font-semibold tracking-wide text-foreground uppercase">
                                    {item.key}
                                </span>
                            ) : (
                                <span className="italic">{label}</span>
                            )}
                            <span className="h-px min-w-4 flex-1 bg-border" />
                            <ChevronDownIcon
                                className={cn(
                                    'size-4 shrink-0 transition-transform',
                                    expanded && 'rotate-180'
                                )}
                            />
                        </button>
                    ) : item.type === ProfileItemType.Spacer ? (
                        <span className="my-1 flex min-w-0 flex-1 items-center justify-center rounded border border-dashed py-1 text-xs text-muted-foreground">
                            {label}
                        </span>
                    ) : (
                        <button
                            type="button"
                            className="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-left"
                            onClick={onToggle}
                            aria-expanded={expanded}
                        >
                            <span className="min-w-0 truncate">{label}</span>
                            {type ? (
                                <span className="shrink-0 font-mono text-[10px] text-muted-foreground">
                                    {type}
                                </span>
                            ) : null}
                            <span className="ml-auto flex shrink-0 items-center gap-1">
                                {item.format ? (
                                    <Badge variant="muted">{item.format}</Badge>
                                ) : null}
                                {/* Shown even if empty by default: flags the exception */}
                                {!item.displayEmpty ? (
                                    <Badge
                                        variant="muted"
                                        title={t(
                                            'profile.hidden_if_empty',
                                            'Hidden when empty'
                                        )}
                                    >
                                        <EyeOffIcon />
                                    </Badge>
                                ) : null}
                                <ChevronDownIcon
                                    className={cn(
                                        'size-4 text-muted-foreground transition-transform',
                                        expanded && 'rotate-180'
                                    )}
                                />
                            </span>
                        </button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onPointerDown={e => e.stopPropagation()}
                        onClick={onRemove}
                        aria-label={t('common.remove', 'Remove')}
                        title={t('common.remove', 'Remove')}
                    >
                        <XIcon />
                    </Button>
                </div>
                {!expanded ? null : item.type === ProfileItemType.Divider ? (
                    <DividerOptions item={item} onChange={onChange} />
                ) : item.type !== ProfileItemType.Spacer ? (
                    <ItemOptions item={item} type={type} onChange={onChange} />
                ) : null}
            </div>
            {insertAfter ? <InsertionMark position="bottom" /> : null}
        </li>
    );
}

function InsertionMark({position}: {position: 'top' | 'bottom'}) {
    return (
        <span
            aria-hidden
            className={cn(
                'pointer-events-none absolute inset-x-0 z-10 h-0.5 rounded-full bg-primary',
                position === 'top' ? '-top-px' : '-bottom-px'
            )}
        />
    );
}

/** The title of a divider, saved when leaving the field */
function DividerOptions({
    item,
    onChange,
}: {
    item: ProfileItem;
    onChange: (data: Partial<ProfileItem>) => void;
}) {
    const {t} = useTranslation();
    const [title, setTitle] = useState(item.key ?? '');
    const save = () => {
        const value = title.trim();
        if (value !== (item.key ?? '')) {
            onChange({key: value});
        }
    };

    return (
        <div className="border-t bg-muted/30 px-3 py-2">
            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                {t('profile.divider.title', 'Title')}
                <Input
                    data-testid="profile-divider-title"
                    className="h-8 flex-1 bg-background"
                    autoFocus
                    value={title}
                    placeholder={t('profile.divider.no_title', 'No title')}
                    onChange={e => setTitle(e.target.value)}
                    onBlur={save}
                    onKeyDown={e => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            save();
                        }
                    }}
                />
            </label>
        </div>
    );
}

function ItemOptions({
    item,
    type,
    onChange,
}: {
    item: ProfileItem;
    type?: string;
    onChange: (data: Partial<ProfileItem>) => void;
}) {
    const {t} = useTranslation();
    const formats = type ? getAttributeType(type).formats(t) : [];

    return (
        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 border-t bg-muted/30 px-3 py-2">
            {formats.length > 0 ? (
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    {t('profile.format', 'Format')}
                    <SimpleSelect
                        className="h-8 w-44"
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
                </label>
            ) : null}
            <LabeledControl
                label={t('profile.show_if_empty', 'Show even if empty')}
            >
                <Checkbox
                    checked={!!item.displayEmpty}
                    onCheckedChange={v => onChange({displayEmpty: v === true})}
                />
            </LabeledControl>
        </div>
    );
}
