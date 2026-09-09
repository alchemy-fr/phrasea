'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ArrowUpDownIcon,
    GripVerticalIcon,
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
import type {SortBy} from '@/types/api';
import {Button} from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {Input} from '@/components/ui/input';
import {useSearch} from '../SearchProvider';
import {BuiltInAttribute, isDefaultSortBy, resolveSortBy} from '../searchState';
import {useDefinitionsBySlug} from '@/features/attributes/definitionsStore';
import {cn} from '@/lib/utils/cn';

type Row = SortBy & {enabled: boolean};

export function SortByButton() {
    const {t} = useTranslation();
    const search = useSearch();
    const [open, setOpen] = useState(false);
    const definitions = useDefinitionsBySlug({
        workspaceId: search.workspaces[0],
    });
    const effective = resolveSortBy(search.sortBy);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant={
                        isDefaultSortBy(search.sortBy) ? 'ghost' : 'secondary'
                    }
                    className="gap-1"
                >
                    <ArrowUpDownIcon />
                    <span className="hidden lg:inline">
                        {t('search.sort.button', 'Sort')}
                    </span>
                    <span className="hidden max-w-48 items-center gap-1 truncate text-xs text-muted-foreground xl:flex">
                        {effective.map(s => (
                            <span
                                key={s.a}
                                className="inline-flex items-center rounded bg-muted px-1"
                            >
                                {definitions[s.a]?.displayName ?? s.a}
                                {s.w === 1 ? (
                                    <ArrowDownIcon className="size-3" />
                                ) : (
                                    <ArrowUpIcon className="size-3" />
                                )}
                            </span>
                        ))}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-[26rem] p-3">
                {open ? <SortEditor onClose={() => setOpen(false)} /> : null}
            </PopoverContent>
        </Popover>
    );
}

function SortEditor({onClose}: {onClose: () => void}) {
    const {t} = useTranslation();
    const search = useSearch();
    const definitions = useDefinitionsBySlug({
        workspaceId: search.workspaces[0],
    });
    const [filter, setFilter] = useState('');
    const [grouped, setGrouped] = useState(search.sortBy.some(s => s.g));

    const sortable = useMemo(
        () =>
            Object.values(definitions)
                .filter(d => d.sortable || d.builtIn)
                .sort((a, b) =>
                    (a.displayName ?? a.name).localeCompare(
                        b.displayName ?? b.name
                    )
                ),
        [definitions]
    );

    const [rows, setRows] = useState<Row[]>([]);
    useEffect(() => {
        const current = resolveSortBy(search.sortBy);
        const active: Row[] = current
            .filter(
                s =>
                    definitions[s.a] ||
                    s.a === BuiltInAttribute.Score ||
                    s.a === BuiltInAttribute.CreatedAt
            )
            .map(s => ({...s, enabled: true}));
        const rest: Row[] = sortable
            .filter(d => !active.some(a => a.a === d.searchSlug))
            .map(d => ({
                a: d.searchSlug,
                w: 1 as const,
                g: false,
                enabled: false,
            }));
        setRows([...active, ...rest]);
    }, [search.sortBy, sortable, definitions]);

    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}})
    );

    const onDragEnd = (e: DragEndEvent) => {
        const {active, over} = e;
        if (!over || active.id === over.id) {
            return;
        }
        setRows(prev => {
            const from = prev.findIndex(r => r.a === active.id);
            const to = prev.findIndex(r => r.a === over.id);
            const moved = arrayMove(prev, from, to);
            moved[to] = {...moved[to], enabled: true};

            return moved;
        });
    };

    const enabledRows = rows.filter(r => r.enabled);
    const firstIsScore = enabledRows[0]?.a === BuiltInAttribute.Score;

    const apply = () => {
        const sortBy: SortBy[] = enabledRows.map((r, i) => ({
            a: r.a,
            w: r.w,
            g: grouped && i === 0 && !firstIsScore,
        }));
        search.setSortBy(sortBy);
        onClose();
    };

    const reset = () => {
        search.setSortBy([]);
        onClose();
    };

    const visible = rows.filter(r => {
        if (!filter) {
            return true;
        }
        const d = definitions[r.a];

        return (d?.displayName ?? d?.name ?? r.a)
            .toLowerCase()
            .includes(filter.toLowerCase());
    });

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">
                    {t('search.sort.title', 'Sort by')}
                </h3>
                <Input
                    value={filter}
                    onChange={e => setFilter(e.target.value)}
                    placeholder={t('common.filter', 'Filter…')}
                    className="h-8 w-40"
                />
            </div>
            <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={onDragEnd}
                >
                    <SortableContext
                        items={visible.map(r => r.a)}
                        strategy={verticalListSortingStrategy}
                    >
                        {visible.map(row => (
                            <SortRow
                                key={row.a}
                                row={row}
                                label={
                                    definitions[row.a]?.displayName ??
                                    definitions[row.a]?.name ??
                                    row.a
                                }
                                onChange={next =>
                                    setRows(prev =>
                                        prev.map(r =>
                                            r.a === row.a ? next : r
                                        )
                                    )
                                }
                            />
                        ))}
                    </SortableContext>
                </DndContext>
            </div>
            <LabeledControl
                label={t('search.sort.group', 'Group by sections')}
                description={
                    firstIsScore
                        ? t(
                              'search.sort.group_disabled',
                              'Not available when sorting by score'
                          )
                        : t(
                              'search.sort.group_help',
                              'Adds separators based on the first sort criterion'
                          )
                }
            >
                <Checkbox
                    checked={grouped && !firstIsScore}
                    disabled={firstIsScore}
                    onCheckedChange={v => setGrouped(v === true)}
                />
            </LabeledControl>
            <div className="flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={reset}>
                    {t('common.reset', 'Reset')}
                </Button>
                <Button size="sm" onClick={apply}>
                    {t('common.apply', 'Apply')}
                </Button>
            </div>
        </div>
    );
}

function SortRow({
    row,
    label,
    onChange,
}: {
    row: Row;
    label: string;
    onChange: (row: Row) => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id: row.a});

    return (
        <div
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={cn(
                'flex items-center gap-2 rounded-md border bg-background px-2 py-1 text-sm',
                !row.enabled && 'opacity-60',
                isDragging && 'z-10 shadow-md'
            )}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground"
                {...attributes}
                {...listeners}
                aria-label="Drag"
            >
                <GripVerticalIcon className="size-4" />
            </button>
            <Switch
                checked={row.enabled}
                onCheckedChange={v => onChange({...row, enabled: v})}
            />
            <span className="min-w-0 flex-1 truncate">{label}</span>
            <Button
                variant="ghost"
                size="icon-xs"
                disabled={!row.enabled}
                onClick={() => onChange({...row, w: row.w === 1 ? 0 : 1})}
                aria-label={row.w === 1 ? 'Descending' : 'Ascending'}
            >
                {row.w === 1 ? <ArrowDownIcon /> : <ArrowUpIcon />}
            </Button>
        </div>
    );
}
