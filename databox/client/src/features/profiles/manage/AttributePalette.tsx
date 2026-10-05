'use client';

import {ReactNode, useCallback, useEffect, useMemo, useState} from 'react';
import {createPortal} from 'react-dom';
import {useTranslation} from 'react-i18next';
import {
    DragOverlay,
    useDndContext,
    useDraggable,
    useDroppable,
} from '@dnd-kit/core';
import {GripVerticalIcon, SearchIcon, Trash2Icon} from 'lucide-react';
import type {
    AttributeDefinitionOrBuiltIn,
    ProfileItem,
    Workspace,
} from '@/types/api';
import {ProfileItemType} from '@/types/api';
import {
    builtInTypes,
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import type {BuiltInAttribute} from '@/features/search/searchState';
import {Input} from '@/components/ui/input';
import {Button} from '@/components/ui/button';
import {snapToCursor} from '@/features/dnd/snapToCursor';
import {cn} from '@/lib/utils/cn';
import {
    PALETTE_ZONE,
    paletteDragId,
    paletteKeyOf,
    type ZoneData,
} from './profileDnd';

/** What a palette entry adds to a profile */
export type PaletteItem = Pick<ProfileItem, 'type' | 'key' | 'definition'>;

export type PaletteEntry = {
    key: string;
    label: string;
    /** Attribute type, shown next to the label */
    type?: string;
    item: PaletteItem;
};

export type PaletteGroup = {id: string; label: string; entries: PaletteEntry[]};

/** Key of an item in the palette: the attribute it shows */
export function paletteKeyOfItem(item: PaletteItem): string {
    switch (item.type) {
        case ProfileItemType.BuiltIn:
            return `b:${item.key}`;
        case ProfileItemType.Definition:
            return `d:${item.definition}`;
        case ProfileItemType.Divider:
            return 'l:divider';
        default:
            return 'l:spacer';
    }
}

export function isLayoutItem(item: Pick<ProfileItem, 'type'>): boolean {
    return (
        item.type === ProfileItemType.Divider ||
        item.type === ProfileItemType.Spacer
    );
}

/**
 * The attribute definitions of the profile editor: loads them, resolves the
 * definition, label and type of a profile item.
 */
export function useProfileDefinitions() {
    const {t} = useTranslation();
    const definitions = useDefinitionsStore(s => s.definitions);
    const builtIn = useDefinitionsStore(s => s.builtIn);
    const load = useDefinitionsStore(s => s.load);
    const workspaces = useCollectionStore(s => s.workspaces);
    const loadWorkspaces = useCollectionStore(s => s.loadWorkspaces);

    useEffect(() => {
        void load();
        void loadWorkspaces();
    }, [load, loadWorkspaces]);

    const definitionOf = useCallback(
        (item: PaletteItem): AttributeDefinitionOrBuiltIn | undefined =>
            item.type === ProfileItemType.BuiltIn
                ? builtIn.find(x => x.searchSlug === item.key)
                : item.type === ProfileItemType.Definition
                  ? definitions.find(x => x.id === item.definition)
                  : undefined,
        [builtIn, definitions]
    );
    const labelOf = useCallback(
        (item: PaletteItem): string => {
            if (item.type === ProfileItemType.Divider) {
                return item.key || t('profile.divider', 'Divider');
            }
            if (item.type === ProfileItemType.Spacer) {
                return t('profile.spacer', 'Spacer');
            }
            const d = definitionOf(item);

            return d?.displayName ?? d?.name ?? item.key ?? '';
        },
        [definitionOf, t]
    );

    return {definitions, builtIn, workspaces, definitionOf, labelOf};
}

export function attributeTypeOf(
    definition: AttributeDefinitionOrBuiltIn | undefined
): string | undefined {
    if (!definition) {
        return undefined;
    }

    return definition.builtIn
        ? (builtInTypes[definition.searchSlug as BuiltInAttribute] ??
              definition.type)
        : definition.type;
}

/**
 * The palette groups: the layout elements (when asked), the built-in
 * attributes, then the definitions of each workspace — without the
 * attributes already `used`, filtered by `query`.
 */
export function usePaletteGroups({
    used,
    query,
    withLayout,
}: {
    used: Set<string>;
    query: string;
    withLayout?: boolean;
}): PaletteGroup[] {
    const {t} = useTranslation();
    const {definitions, builtIn, workspaces} = useProfileDefinitions();

    return useMemo(() => {
        const q = query.trim().toLowerCase();
        const keep = (e: PaletteEntry) =>
            !used.has(e.key) && (!q || e.label.toLowerCase().includes(q));
        const entryOf = (
            d: AttributeDefinitionOrBuiltIn,
            item: PaletteItem
        ): PaletteEntry => ({
            key: paletteKeyOfItem(item),
            label: d.displayName ?? d.name,
            type: attributeTypeOf(d),
            item,
        });

        const groups: PaletteGroup[] = [];
        if (withLayout) {
            groups.push({
                id: 'layout',
                label: t('profile.palette.layout', 'Layout'),
                entries: [
                    {
                        key: 'l:divider',
                        label: t('profile.divider', 'Divider'),
                        item: {type: ProfileItemType.Divider},
                    },
                    {
                        key: 'l:spacer',
                        label: t('profile.spacer', 'Spacer'),
                        item: {type: ProfileItemType.Spacer},
                    },
                ].filter(e => !q || e.label.toLowerCase().includes(q)),
            });
        }
        groups.push({
            id: 'built-in',
            label: t('search.condition.built_in', 'Built-in'),
            entries: builtIn
                .map(b =>
                    entryOf(b, {
                        type: ProfileItemType.BuiltIn,
                        key: b.searchSlug,
                    })
                )
                .filter(keep),
        });
        workspaces.forEach((ws: Workspace) => {
            groups.push({
                id: ws.id,
                label: ws.displayName ?? ws.name,
                entries: definitions
                    .filter(d => workspaceIdOf(d) === ws.id)
                    .map(d =>
                        entryOf(d, {
                            type: ProfileItemType.Definition,
                            definition: d.id,
                        })
                    )
                    .filter(keep),
            });
        });

        return groups.filter(g => g.entries.length > 0);
    }, [builtIn, definitions, workspaces, used, query, withLayout, t]);
}

/**
 * The attributes that can be added to a profile section, grouped, to drag
 * into the section (or add with the row button). It is a drop zone too:
 * dropping a placed item back onto it removes the item.
 */
export function AttributePalette({
    groups,
    query,
    onQueryChange,
    onAdd,
    addIcon,
    addLabel,
    hint,
}: {
    groups: PaletteGroup[];
    query: string;
    onQueryChange: (query: string) => void;
    onAdd: (entry: PaletteEntry) => void;
    addIcon: ReactNode;
    addLabel: string;
    hint?: ReactNode;
}) {
    const {t} = useTranslation();
    const {active} = useDndContext();
    const {setNodeRef, isOver} = useDroppable({
        id: PALETTE_ZONE,
        data: {kind: 'zone'} satisfies ZoneData,
    });
    // A placed item is dragged: the palette takes it back
    const removing = !!active && !paletteKeyOf(active.id);

    return (
        <section
            ref={setNodeRef}
            data-testid="profile-palette"
            className={cn(
                'relative flex max-h-[60vh] min-h-0 flex-col gap-2 rounded-lg border bg-muted/30 p-2 transition-colors md:max-h-none',
                removing && 'border-dashed border-destructive/50',
                removing && isOver && 'bg-destructive/10'
            )}
        >
            <div className="relative">
                <SearchIcon className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={e => onQueryChange(e.target.value)}
                    placeholder={t(
                        'profile.palette.search',
                        'Search attributes…'
                    )}
                    className="h-8 bg-background pl-7"
                />
            </div>
            {hint ? (
                <p className="px-1 text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <div className="min-h-0 flex-1 overflow-y-auto">
                {groups.length === 0 ? (
                    <p className="px-1 py-6 text-center text-xs text-muted-foreground">
                        {query.trim()
                            ? t(
                                  'profile.palette.no_match',
                                  'No matching attribute'
                              )
                            : t(
                                  'profile.palette.empty',
                                  'Every attribute is already placed'
                              )}
                    </p>
                ) : null}
                {groups.map(g => (
                    <div key={g.id} className="mb-2 last:mb-0">
                        <div className="sticky top-0 z-[1] bg-muted/95 px-1 py-1 text-[11px] font-semibold text-muted-foreground uppercase backdrop-blur-sm">
                            {g.label}
                        </div>
                        <ul>
                            {g.entries.map(e => (
                                <PaletteRow
                                    key={e.key}
                                    entry={e}
                                    onAdd={() => onAdd(e)}
                                    addIcon={addIcon}
                                    addLabel={addLabel}
                                />
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
            {removing ? (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center rounded-lg bg-background/70 text-sm font-medium text-destructive">
                    <Trash2Icon className="mr-2 size-4" />
                    {t('profile.palette.drop_to_remove', 'Drop here to remove')}
                </div>
            ) : null}
        </section>
    );
}

function PaletteRow({
    entry,
    onAdd,
    addIcon,
    addLabel,
}: {
    entry: PaletteEntry;
    onAdd: () => void;
    addIcon: ReactNode;
    addLabel: string;
}) {
    const {attributes, listeners, setNodeRef, isDragging} = useDraggable({
        id: paletteDragId(entry.key),
        data: {entry},
    });

    return (
        <li
            ref={setNodeRef}
            data-testid="palette-entry"
            data-key={entry.key}
            {...attributes}
            {...listeners}
            onDoubleClick={onAdd}
            className={cn(
                'group/pe flex scroll-mt-8 cursor-grab touch-none items-center gap-1.5 rounded-md py-1 pr-1 pl-0.5 text-sm select-none hover:bg-background focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none active:cursor-grabbing',
                isDragging && 'opacity-40'
            )}
        >
            <GripVerticalIcon className="size-3.5 shrink-0 text-muted-foreground/50 group-hover/pe:text-muted-foreground" />
            <span className="min-w-0 flex-1 truncate">{entry.label}</span>
            {entry.type ? (
                <span className="shrink-0 font-mono text-[10px] text-muted-foreground">
                    {entry.type}
                </span>
            ) : null}
            <Button
                variant="ghost"
                size="icon-xs"
                className="size-6 opacity-60 group-hover/pe:opacity-100"
                onPointerDown={e => e.stopPropagation()}
                onClick={onAdd}
                aria-label={addLabel}
                title={addLabel}
            >
                {addIcon}
            </Button>
        </li>
    );
}

/**
 * The ghost of the dragged attribute, following the pointer. Rendered on the
 * body, above the dialog: inside it, the dialog transform would offset it.
 */
export function ProfileDragOverlay({label}: {label: string | null}) {
    // No body to render into on the server
    const [mounted, setMounted] = useState(false);
    useEffect(() => setMounted(true), []);

    if (!mounted) {
        return null;
    }

    return createPortal(
        <DragOverlay
            zIndex={60}
            modifiers={[snapToCursor]}
            dropAnimation={null}
        >
            {label !== null ? (
                <div
                    data-testid="profile-drag-ghost"
                    className="pointer-events-none inline-flex max-w-64 cursor-grabbing items-center gap-1.5 rounded-md border bg-popover px-2 py-1 text-sm font-medium whitespace-nowrap shadow-lg ring-1 ring-primary/40"
                >
                    <GripVerticalIcon className="size-3.5 shrink-0 text-muted-foreground" />
                    <span className="truncate">{label}</span>
                </div>
            ) : null}
        </DragOverlay>,
        document.body
    );
}
