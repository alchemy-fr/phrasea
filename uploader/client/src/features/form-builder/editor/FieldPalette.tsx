'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useDraggable} from '@dnd-kit/core';
import {GripVerticalIcon, PlusIcon, SearchIcon} from 'lucide-react';
import {Input} from '@alchemy/next-ui/ui/input';
import {Button} from '@alchemy/next-ui/ui/button';
import {Badge} from '@alchemy/next-ui/ui/misc';
import {cn} from '@alchemy/next-ui/utils/cn';
import {paletteDragId} from './dnd';

export type PaletteItem = {
    id: string;
    label: string;
    /** Shown beside the label: the key or the type */
    hint?: string;
    /** Already in the form */
    used?: boolean;
};

export type PaletteGroup = {
    id: string;
    title: string;
    items: PaletteItem[];
    /** Shown instead of the items (loading, error, nothing to offer) */
    status?: ReactNode;
};

export function FieldPalette({
    groups,
    onAdd,
    disabled,
}: {
    groups: PaletteGroup[];
    onAdd: (itemId: string) => void;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const [search, setSearch] = useState('');
    const query = search.trim().toLowerCase();

    return (
        <aside
            data-testid="builder-palette"
            className="flex flex-col gap-4 rounded-lg border p-3"
        >
            <div className="relative">
                <SearchIcon className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    placeholder={t('builder.palette.search', 'Search a field…')}
                    className="pl-8"
                />
            </div>
            {groups.map(group => {
                const items = query
                    ? group.items.filter(
                          i =>
                              i.label.toLowerCase().includes(query) ||
                              i.hint?.toLowerCase().includes(query)
                      )
                    : group.items;

                return (
                    <section key={group.id}>
                        <h3 className="mb-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {group.title}
                        </h3>
                        {group.status ?? (
                            <ul className="flex flex-col gap-1">
                                {items.map(item => (
                                    <PaletteRow
                                        key={item.id}
                                        item={item}
                                        onAdd={() => onAdd(item.id)}
                                        disabled={disabled}
                                    />
                                ))}
                                {items.length === 0 ? (
                                    <li className="text-xs text-muted-foreground">
                                        {t(
                                            'builder.palette.none',
                                            'Nothing to add'
                                        )}
                                    </li>
                                ) : null}
                            </ul>
                        )}
                    </section>
                );
            })}
        </aside>
    );
}

function PaletteRow({
    item,
    onAdd,
    disabled,
}: {
    item: PaletteItem;
    onAdd: () => void;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const unavailable = disabled || item.used;
    const {attributes, listeners, setNodeRef, isDragging} = useDraggable({
        id: paletteDragId(item.id),
        disabled: unavailable,
    });

    return (
        <li
            ref={setNodeRef}
            data-testid="builder-palette-item"
            className={cn(
                'group flex items-center gap-1.5 rounded-md border bg-background px-1.5 py-1 text-sm',
                unavailable ? 'opacity-50' : 'hover:bg-muted',
                isDragging && 'opacity-40'
            )}
        >
            <span
                {...attributes}
                {...listeners}
                className={cn(
                    'text-muted-foreground',
                    unavailable ? 'cursor-not-allowed' : 'cursor-grab'
                )}
                aria-label={t('builder.drag', 'Drag')}
            >
                <GripVerticalIcon className="size-4" />
            </span>
            <span className="min-w-0 flex-1 truncate" title={item.label}>
                {item.label}
            </span>
            {item.hint ? (
                <Badge
                    variant="outline"
                    className="max-w-24 truncate font-mono text-[10px]"
                >
                    {item.hint}
                </Badge>
            ) : null}
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                disabled={unavailable}
                onClick={onAdd}
                aria-label={t('builder.palette.add', 'Add {{label}}', {
                    label: item.label,
                })}
            >
                <PlusIcon />
            </Button>
        </li>
    );
}

export function PaletteDragPreview({item}: {item: PaletteItem}) {
    return (
        <div className="flex cursor-grabbing items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-sm shadow-lg">
            <GripVerticalIcon className="size-4 text-muted-foreground" />
            {item.label}
        </div>
    );
}
