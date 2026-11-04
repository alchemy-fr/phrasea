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
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import type {SortBy} from '@/types/api';
import {Button} from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {FilterInput} from '@/components/ui/filter-input';
import {useSearch} from '../SearchProvider';
import {BuiltInAttribute, isDefaultSortBy, resolveSortBy} from '../searchState';
import {useDefinitionsBySearchSlug} from '@/features/attributes/definitionsStore';
import {AttributeDefinitionSelect} from '@/components/form/selects';
import {cn} from '@/lib/utils/cn';
import {
    overlayRow,
    SortableOverlay,
    SortableRow,
    sortableMeasuring,
    useSortableRow,
} from '@/components/ui/sortable';

type Row = SortBy & {enabled: boolean};

export function SortByButton() {
    const {t} = useTranslation();
    const search = useSearch();
    const [open, setOpen] = useState(false);
    const effective = useMemo(
        () => resolveSortBy(search.sortBy),
        [search.sortBy]
    );
    const searchSlugs = useMemo(() => effective.map(s => s.a), [effective]);
    const definitions = useDefinitionsBySearchSlug({
        workspaceId: search.workspaces[0],
        searchSlugs,
    });

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    data-testid="sort-button"
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
    const workspaceId = search.workspaces[0];
    const [rows, setRows] = useState<Row[]>(() =>
        resolveSortBy(search.sortBy).map(s => ({...s, enabled: true}))
    );
    const searchSlugs = useMemo(() => rows.map(r => r.a), [rows]);
    const definitions = useDefinitionsBySearchSlug({workspaceId, searchSlugs});
    const [filter, setFilter] = useState('');
    const [grouped, setGrouped] = useState(search.sortBy.some(s => s.g));

    // The other sortable attributes are added from a select searched by the
    // API: only the built-ins are listed
    const sortableBuiltIns = useMemo(
        () =>
            Object.values(definitions)
                .filter(d => d.builtIn && d.sortable)
                .sort((a, b) =>
                    (a.displayName ?? a.name).localeCompare(
                        b.displayName ?? b.name
                    )
                ),
        [definitions]
    );
    useEffect(() => {
        setRows(prev => {
            const missing = sortableBuiltIns.filter(
                d => !prev.some(r => r.a === d.searchSlug)
            );

            return missing.length === 0
                ? prev
                : [
                      ...prev,
                      ...missing.map(d => ({
                          a: d.searchSlug,
                          w: 1 as const,
                          g: false,
                          enabled: false,
                      })),
                  ];
        });
    }, [sortableBuiltIns]);

    const addSort = (searchSlug: string) =>
        setRows(prev =>
            prev.some(r => r.a === searchSlug)
                ? prev
                : [
                      ...prev.filter(r => r.enabled),
                      {a: searchSlug, w: 1, g: false, enabled: true},
                      ...prev.filter(r => !r.enabled),
                  ]
        );

    // A sort on an attribute that is not sortable anymore is dropped
    const sortableRows = rows.filter(
        r =>
            r.a === BuiltInAttribute.Score ||
            r.a === BuiltInAttribute.CreatedAt ||
            !definitions[r.a] ||
            definitions[r.a].sortable
    );

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

    const enabledRows = sortableRows.filter(r => r.enabled);
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

    const visible = sortableRows.filter(r => {
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
                <FilterInput
                    value={filter}
                    onValueChange={setFilter}
                    className="w-40"
                />
            </div>
            <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
                <DndContext
                    measuring={sortableMeasuring}
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
                    <SortableOverlay>
                        {id => {
                            const row = visible.find(r => r.a === id);

                            return row ? (
                                <SortRowView
                                    row={row}
                                    drag={overlayRow}
                                    label={
                                        definitions[row.a]?.displayName ??
                                        definitions[row.a]?.name ??
                                        row.a
                                    }
                                    onChange={() => undefined}
                                />
                            ) : null;
                        }}
                    </SortableOverlay>
                </DndContext>
            </div>
            <AttributeDefinitionSelect
                workspaceId={workspaceId}
                filters={{sortable: true, enabled: true}}
                postFilter={d => !rows.some(r => r.a === d.searchSlug)}
                value={undefined}
                onChange={(_v, d) => d && addSort(d.searchSlug)}
                placeholder={t('search.sort.add', 'Add a sort…')}
            />
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

type SortRowProps = {
    row: Row;
    label: string;
    onChange: (row: Row) => void;
};

function SortRow(props: SortRowProps) {
    const drag = useSortableRow(props.row.a);

    return <SortRowView {...props} drag={drag} />;
}

/** A criterion of the list, also rendered as the copy following the pointer */
function SortRowView({
    row,
    label,
    onChange,
    drag,
}: SortRowProps & {drag: SortableRow}) {
    return (
        <div
            ref={drag.nodeRef}
            style={drag.style}
            className={cn(
                'flex items-center gap-2 rounded-md border bg-background px-2 py-1 text-sm',
                !row.enabled && 'opacity-60',
                drag.className
            )}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground"
                {...drag.handle}
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
