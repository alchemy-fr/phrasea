'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ArrowRightIcon,
    ArrowLeftIcon,
    GripVerticalIcon,
    MinusIcon,
    SpaceIcon,
    XIcon,
    EyeIcon,
} from 'lucide-react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {toast} from 'sonner';
import type {AttributeDefinitionOrBuiltIn, ProfileItem} from '@/types/api';
import {ProfileItemSection, ProfileItemType} from '@/types/api';
import type {ProfileTabProps} from './ProfileManageRoute';
import {
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {
    addToProfile,
    putProfileItem,
    removeFromProfile,
    sortProfileItems,
} from '@/lib/api/misc';
import {useProfileStore} from '../profileStore';
import {Input} from '@/components/ui/input';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {getAttributeType} from '@/features/attributes/types/registry';
import {builtInTypes} from '@/features/attributes/definitionsStore';
import type {BuiltInAttribute} from '@/features/search/searchState';
import {cn} from '@/lib/utils/cn';

/**
 * Transfer list: available definitions (built-in + per workspace) on the
 * left, displayed (pinned) items on the right, sortable, with dividers /
 * spacers and per-item options (format, show if empty).
 */
export function OrganizeProfileTab({profile, refresh}: ProfileTabProps) {
    const {t} = useTranslation();
    const {definitions, builtIn, load} = useDefinitionsStore();
    const workspaces = useCollectionStore(s => s.workspaces);
    const loadWorkspaces = useCollectionStore(s => s.loadWorkspaces);
    const upsert = useProfileStore(s => s.upsert);
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<string | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}})
    );

    useEffect(() => {
        void load();
        void loadWorkspaces();
    }, [load, loadWorkspaces]);

    const items = useMemo(
        () =>
            (profile.items ?? []).filter(
                i => i.section === ProfileItemSection.Attributes
            ),
        [profile.items]
    );
    const used = useMemo(
        () => new Set(items.map(i => i.definition ?? i.key)),
        [items]
    );

    const available = useMemo(() => {
        const q = query.toLowerCase();
        const match = (d: AttributeDefinitionOrBuiltIn) =>
            !q || (d.displayName ?? d.name).toLowerCase().includes(q);

        return {
            builtIn: builtIn.filter(b => !used.has(b.searchSlug) && match(b)),
            byWorkspace: workspaces.map(ws => ({
                workspace: ws,
                definitions: definitions.filter(
                    d =>
                        workspaceIdOf(d) === ws.id &&
                        !used.has(d.id) &&
                        match(d)
                ),
            })),
        };
    }, [builtIn, definitions, workspaces, used, query]);

    const apply = (updated: Awaited<ReturnType<typeof addToProfile>>) => {
        upsert(updated);
        refresh();
    };

    const add = async (item: Omit<ProfileItem, 'id' | 'section'>) => {
        try {
            apply(
                await addToProfile(profile.id, [
                    {...item, id: '', section: ProfileItemSection.Attributes},
                ])
            );
        } catch (e: any) {
            toast.error(e?.message);
        }
    };
    const remove = async (id: string) => {
        try {
            apply(await removeFromProfile(profile.id, [id]));
        } catch (e: any) {
            toast.error(e?.message);
        }
    };
    const onDragEnd = async (e: DragEndEvent) => {
        const {active, over} = e;
        if (!over || active.id === over.id) {
            return;
        }
        const ordered = arrayMove(
            items.map(i => i.id),
            items.findIndex(i => i.id === active.id),
            items.findIndex(i => i.id === over.id)
        );
        const others = (profile.items ?? [])
            .filter(i => i.section !== ProfileItemSection.Attributes)
            .map(i => i.id);
        await sortProfileItems(profile.id, [...ordered, ...others]);
        refresh();
    };
    const update = async (id: string, data: Partial<ProfileItem>) => {
        await putProfileItem(profile.id, id, data);
        refresh();
    };

    const labelOf = (item: ProfileItem) => {
        if (item.type === ProfileItemType.Divider)
            return t('profile.divider', 'Divider');
        if (item.type === ProfileItemType.Spacer)
            return t('profile.spacer', 'Spacer');
        if (item.type === ProfileItemType.BuiltIn) {
            const b = builtIn.find(x => x.searchSlug === item.key);

            return b?.displayName ?? b?.name ?? item.key ?? '';
        }
        const d = definitions.find(x => x.id === item.definition);

        return d?.displayName ?? d?.name ?? item.definition ?? '';
    };
    const definitionOf = (
        item: ProfileItem
    ): AttributeDefinitionOrBuiltIn | undefined =>
        item.type === ProfileItemType.BuiltIn
            ? builtIn.find(x => x.searchSlug === item.key)
            : definitions.find(x => x.id === item.definition);

    const selectedItem = items.find(i => i.id === selected);

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_1fr_minmax(14rem,0.8fr)]">
            <div className="space-y-2">
                <h4 className="text-xs font-semibold text-muted-foreground uppercase">
                    {t('profile.available', 'Available attributes')}
                </h4>
                <Input
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t('common.search', 'Search…')}
                    className="h-8"
                />
                <div className="max-h-[55vh] space-y-3 overflow-y-auto rounded-md border p-2">
                    <div>
                        <div className="mb-1 text-[11px] font-semibold text-muted-foreground uppercase">
                            {t('search.condition.built_in', 'Built-in')}
                        </div>
                        {available.builtIn.map(b => (
                            <AvailableRow
                                key={b.searchSlug}
                                label={b.displayName ?? b.name}
                                type={
                                    builtInTypes[
                                        b.searchSlug as BuiltInAttribute
                                    ] ?? b.type
                                }
                                onAdd={() =>
                                    add({
                                        type: ProfileItemType.BuiltIn,
                                        key: b.searchSlug,
                                    })
                                }
                            />
                        ))}
                    </div>
                    {available.byWorkspace.map(
                        ({workspace, definitions: defs}) =>
                            defs.length > 0 ? (
                                <div key={workspace.id}>
                                    <div className="mb-1 text-[11px] font-semibold text-muted-foreground uppercase">
                                        {workspace.displayName ??
                                            workspace.name}
                                    </div>
                                    {defs.map(d => (
                                        <AvailableRow
                                            key={d.id}
                                            label={d.displayName ?? d.name}
                                            type={d.type}
                                            onAdd={() =>
                                                add({
                                                    type: ProfileItemType.Definition,
                                                    definition: d.id,
                                                })
                                            }
                                        />
                                    ))}
                                </div>
                            ) : null
                    )}
                </div>
            </div>
            <div className="space-y-2">
                <div className="flex items-center gap-1">
                    <h4 className="flex-1 text-xs font-semibold text-muted-foreground uppercase">
                        {t('profile.displayed', 'Displayed')}
                    </h4>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => add({type: ProfileItemType.Divider})}
                    >
                        <MinusIcon /> {t('profile.divider', 'Divider')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => add({type: ProfileItemType.Spacer})}
                    >
                        <SpaceIcon /> {t('profile.spacer', 'Spacer')}
                    </Button>
                </div>
                <div className="max-h-[55vh] overflow-y-auto rounded-md border p-2">
                    {items.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {t(
                                'profile.no_items',
                                'Add attributes from the left column'
                            )}
                        </p>
                    ) : null}
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onDragEnd}
                    >
                        <SortableContext
                            items={items.map(i => i.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            <ul className="space-y-1">
                                {items.map(item => (
                                    <SortableRow
                                        key={item.id}
                                        id={item.id}
                                        active={selected === item.id}
                                        onClick={() => setSelected(item.id)}
                                        onRemove={() => remove(item.id)}
                                        muted={
                                            item.type ===
                                                ProfileItemType.Divider ||
                                            item.type === ProfileItemType.Spacer
                                        }
                                    >
                                        {labelOf(item)}
                                        {item.displayEmpty ? (
                                            <Badge
                                                variant="muted"
                                                className="ml-2"
                                            >
                                                <EyeIcon />{' '}
                                                {t(
                                                    'profile.show_if_empty_short',
                                                    'empty'
                                                )}
                                            </Badge>
                                        ) : null}
                                    </SortableRow>
                                ))}
                            </ul>
                        </SortableContext>
                    </DndContext>
                </div>
            </div>
            <div className="rounded-md border p-3 text-sm">
                {selectedItem &&
                selectedItem.type !== ProfileItemType.Divider &&
                selectedItem.type !== ProfileItemType.Spacer ? (
                    <ItemOptions
                        item={selectedItem}
                        definition={definitionOf(selectedItem)}
                        onChange={data => update(selectedItem.id, data)}
                    />
                ) : (
                    <p className="text-muted-foreground">
                        {t(
                            'profile.select_item',
                            'Select a displayed attribute to set its options.'
                        )}
                    </p>
                )}
            </div>
        </div>
    );
}

function AvailableRow({
    label,
    type,
    onAdd,
}: {
    label: string;
    type: string;
    onAdd: () => void;
}) {
    return (
        <div className="group/av flex items-center gap-2 rounded px-1 py-1 text-sm hover:bg-accent/60">
            <span className="min-w-0 flex-1 truncate">{label}</span>
            <Badge variant="muted">{type}</Badge>
            <Button
                variant="ghost"
                size="icon-xs"
                onClick={onAdd}
                aria-label="Add"
            >
                <ArrowRightIcon />
            </Button>
        </div>
    );
}

function SortableRow({
    id,
    active,
    onClick,
    onRemove,
    muted,
    children,
}: {
    id: string;
    active: boolean;
    onClick: () => void;
    onRemove: () => void;
    muted?: boolean;
    children: React.ReactNode;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id});

    return (
        <li
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={cn(
                'flex items-center gap-1 rounded-md border bg-card pr-1 text-sm',
                active && 'border-primary bg-primary/5',
                isDragging && 'z-10 shadow-md',
                muted && 'text-muted-foreground italic'
            )}
        >
            <button
                type="button"
                className="cursor-grab px-1 text-muted-foreground"
                {...attributes}
                {...listeners}
                aria-label="Drag"
            >
                <GripVerticalIcon className="size-4" />
            </button>
            <button
                type="button"
                className="flex min-w-0 flex-1 items-center truncate px-1 py-1.5 text-left"
                onClick={onClick}
            >
                {children}
            </button>
            <Button
                variant="ghost"
                size="icon-xs"
                onClick={onRemove}
                aria-label="Remove"
            >
                <ArrowLeftIcon />
            </Button>
        </li>
    );
}

export function ItemOptions({
    item,
    definition,
    onChange,
}: {
    item: ProfileItem;
    definition?: AttributeDefinitionOrBuiltIn;
    onChange: (data: Partial<ProfileItem>) => void;
}) {
    const {t} = useTranslation();
    const type = definition?.builtIn
        ? (builtInTypes[definition.searchSlug as BuiltInAttribute] ??
          definition.type)
        : definition?.type;
    const formats = type ? getAttributeType(type).formats(t) : [];

    return (
        <div className="space-y-3">
            <h4 className="font-semibold">
                {definition?.displayName ?? definition?.name}
            </h4>
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

export {XIcon};
