'use client';

import {
    type KeyboardEvent,
    type ReactNode,
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import {
    type Column,
    type ColumnDef,
    type ColumnFiltersState,
    type OnChangeFn,
    type Row,
    type RowSelectionState,
    type SortingState,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {useVirtualizer} from '@tanstack/react-virtual';
import {ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {FilterInput} from '@/components/ui/filter-input';
import {InlineLoader} from '@/components/ui/loader';
import {useInfiniteScroll} from '@/hooks/useInfiniteScroll';
import {cn} from '@/lib/utils/cn';
import type {CellEditorRenderProps, CellPosition} from './types';

export type DataTableProps<T> = {
    'data': T[];
    'columns': ColumnDef<T, any>[];
    'getRowId': (row: T) => string;
    'sorting': SortingState;
    'onSortingChange': OnChangeFn<SortingState>;
    'columnFilters'?: ColumnFiltersState;
    'onColumnFiltersChange'?: OnChangeFn<ColumnFiltersState>;
    'rowSelection'?: RowSelectionState;
    'onRowSelectionChange'?: OnChangeFn<RowSelectionState>;
    /** Called when an edited cell is committed with a different value */
    'onCellCommit'?: (
        row: T,
        columnId: string,
        value: string
    ) => void | Promise<void>;
    'renderEditor'?: (props: CellEditorRenderProps<T>) => ReactNode;
    'rowClassName'?: (row: T) => string | undefined;
    'estimateRowHeight'?: number;
    'loading'?: boolean;
    'loadingMore'?: boolean;
    'hasMore'?: boolean;
    'onReachEnd'?: () => void;
    'emptyLabel'?: ReactNode;
    'className'?: string;
    'data-testid'?: string;
    /** Moves the focus (and optionally starts editing), e.g. on a row just added */
    'requestFocus'?: (CellPosition & {edit?: boolean}) | null;
};

type EditingCell = CellPosition & {initialValue?: string};

const PAGE_ROWS = 20;

const alignClass = {
    left: 'justify-start text-left',
    right: 'justify-end text-right',
    center: 'justify-center text-center',
};

/**
 * Headless spreadsheet-like table: virtualized rows, server-side sorting and
 * filtering (the parent fetches), row selection and keyboard navigation with
 * in-cell edition.
 *
 * The grid element keeps the DOM focus while navigating (the focused cell is
 * only highlighted); an editor rendered by `renderEditor` takes it while a cell
 * is edited and gives it back once committed or cancelled.
 */
export function DataTable<T>({
    data,
    columns,
    getRowId,
    sorting,
    onSortingChange,
    columnFilters,
    onColumnFiltersChange,
    rowSelection,
    onRowSelectionChange,
    onCellCommit,
    renderEditor,
    rowClassName,
    estimateRowHeight = 36,
    loading,
    loadingMore,
    hasMore,
    onReachEnd,
    emptyLabel,
    className,
    'data-testid': testId,
    requestFocus,
}: DataTableProps<T>) {
    const {t} = useTranslation();
    const gridId = useId();
    const table = useReactTable({
        data,
        columns,
        getRowId: row => getRowId(row),
        state: {
            sorting,
            columnFilters: columnFilters ?? [],
            rowSelection: rowSelection ?? {},
        },
        onSortingChange,
        onColumnFiltersChange,
        onRowSelectionChange,
        manualSorting: true,
        manualFiltering: true,
        enableMultiSort: false,
        enableSortingRemoval: false,
        enableRowSelection: true,
        enableColumnResizing: true,
        columnResizeMode: 'onChange',
        getCoreRowModel: getCoreRowModel(),
    });
    const rows = table.getRowModel().rows;
    const navigable = table
        .getVisibleLeafColumns()
        .filter(c => !c.columnDef.meta?.skipFocus)
        .map(c => c.id);

    const scrollRef = useRef<HTMLDivElement | null>(null);
    const [scrollElement, setScrollElement] = useState<HTMLDivElement | null>(
        null
    );
    const setScrollRef = useCallback((el: HTMLDivElement | null) => {
        scrollRef.current = el;
        setScrollElement(el);
    }, []);

    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scrollElement,
        estimateSize: () => estimateRowHeight,
        overscan: 10,
        getItemKey: i => rows[i]?.id ?? i,
    });

    const sentinelRef = useInfiniteScroll(
        scrollRef,
        onReachEnd,
        !!hasMore && !loadingMore
    );

    const [focused, setFocused] = useState<CellPosition | null>(null);
    const [editing, setEditingState] = useState<EditingCell | null>(null);
    // Read by the commit callbacks: a blur following a commit or a cancel
    // must not commit again
    const editingRef = useRef<EditingCell | null>(null);
    const setEditing = (cell: EditingCell | null) => {
        editingRef.current = cell;
        setEditingState(cell);
    };

    const focusGrid = () => scrollRef.current?.focus({preventScroll: true});

    const moveTo = (pos: CellPosition) => {
        const rowIndex = Math.max(0, Math.min(rows.length - 1, pos.rowIndex));
        setFocused({rowIndex, columnId: pos.columnId});
        if (rows.length > 0) {
            virtualizer.scrollToIndex(rowIndex, {align: 'auto'});
        }
    };

    const canEdit = (row: Row<T>, columnId: string): boolean => {
        const meta = table.getColumn(columnId)?.columnDef.meta;

        return (
            !!meta?.editor &&
            !!renderEditor &&
            meta.editable?.(row.original) !== false
        );
    };

    const startEditing = (pos: CellPosition, initialValue?: string) => {
        const row = rows[pos.rowIndex];
        if (row && canEdit(row, pos.columnId)) {
            setFocused(pos);
            setEditing({...pos, initialValue});

            return true;
        }

        return false;
    };

    const valueOf = (row: Row<T>, columnId: string): string => {
        const column = table.getColumn(columnId);
        const getValue = column?.columnDef.meta?.getValue;
        if (getValue) {
            return getValue(row.original);
        }
        const v = row.getValue(columnId);

        return v === undefined || v === null ? '' : String(v);
    };

    const commit = (pos: CellPosition, value: string) => {
        const current = editingRef.current;
        if (
            !current ||
            current.rowIndex !== pos.rowIndex ||
            current.columnId !== pos.columnId
        ) {
            return;
        }
        setEditing(null);
        const row = rows[pos.rowIndex];
        if (row && value !== valueOf(row, pos.columnId)) {
            void onCellCommit?.(row.original, pos.columnId, value);
        }
    };

    const cancel = () => setEditing(null);

    // The editor unmounted with the focus: give it back to the grid (not when
    // the user moved it elsewhere, which committed the cell)
    useEffect(() => {
        if (editing) {
            return;
        }
        const active = document.activeElement;
        if (focused && (!active || active === document.body)) {
            focusGrid();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing]);

    // Escape cancels the edition without closing a dialog around the grid:
    // Radix listens to Escape on the document, in the capture phase
    useEffect(() => {
        if (!editing) {
            return;
        }
        const onKeyDown = (e: globalThis.KeyboardEvent) => {
            if (
                e.key === 'Escape' &&
                e.target instanceof Node &&
                scrollRef.current?.contains(e.target)
            ) {
                e.stopPropagation();
                e.preventDefault();
                cancel();
            }
        };
        window.addEventListener('keydown', onKeyDown, {capture: true});

        return () =>
            window.removeEventListener('keydown', onKeyDown, {capture: true});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing]);

    useEffect(() => {
        if (!requestFocus) {
            return;
        }
        const {edit, ...pos} = requestFocus;
        setFocused(pos);
        setEditing(edit ? pos : null);
        virtualizer.scrollToIndex(pos.rowIndex, {align: 'auto'});
        if (!edit) {
            focusGrid();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [requestFocus]);

    const nextColumn = (columnId: string, delta: number): string | undefined =>
        navigable[navigable.indexOf(columnId) + delta];

    const tabFrom = (pos: CellPosition, back: boolean) => {
        const next = nextColumn(pos.columnId, back ? -1 : 1);
        if (next) {
            moveTo({rowIndex: pos.rowIndex, columnId: next});
        } else if (back && pos.rowIndex > 0) {
            moveTo({
                rowIndex: pos.rowIndex - 1,
                columnId: navigable[navigable.length - 1],
            });
        } else if (!back && pos.rowIndex < rows.length - 1) {
            moveTo({rowIndex: pos.rowIndex + 1, columnId: navigable[0]});
        }
    };

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        const target = e.target as HTMLElement;
        const editedCell = target.closest('[data-editing]');
        if (editedCell) {
            const pos = editingRef.current ?? editing;
            if (e.key === 'Tab' && pos) {
                e.preventDefault();
                // An editor not committing on Tab (no change) is left as is
                if (editingRef.current) {
                    setEditing(null);
                }
                tabFrom(pos, e.shiftKey);
                focusGrid();
            }

            return;
        }
        if (target !== scrollRef.current || rows.length === 0) {
            return;
        }
        if (!focused) {
            if (
                navigable.length > 0 &&
                [
                    'ArrowUp',
                    'ArrowDown',
                    'ArrowLeft',
                    'ArrowRight',
                    'Home',
                    'End',
                ].includes(e.key)
            ) {
                e.preventDefault();
                moveTo({rowIndex: 0, columnId: navigable[0]});
            }

            return;
        }

        const {rowIndex, columnId} = focused;
        switch (e.key) {
            case 'ArrowUp':
                e.preventDefault();
                moveTo({rowIndex: rowIndex - 1, columnId});
                break;
            case 'ArrowDown':
                e.preventDefault();
                moveTo({rowIndex: rowIndex + 1, columnId});
                break;
            case 'ArrowLeft':
            case 'ArrowRight': {
                e.preventDefault();
                const next = nextColumn(
                    columnId,
                    e.key === 'ArrowLeft' ? -1 : 1
                );
                if (next) {
                    moveTo({rowIndex, columnId: next});
                }
                break;
            }
            case 'Tab':
                e.preventDefault();
                tabFrom(focused, e.shiftKey);
                break;
            case 'Home':
                e.preventDefault();
                moveTo(
                    e.ctrlKey || e.metaKey
                        ? {rowIndex: 0, columnId}
                        : {rowIndex, columnId: navigable[0]}
                );
                break;
            case 'End':
                e.preventDefault();
                moveTo(
                    e.ctrlKey || e.metaKey
                        ? {rowIndex: rows.length - 1, columnId}
                        : {rowIndex, columnId: navigable[navigable.length - 1]}
                );
                break;
            case 'PageUp':
                e.preventDefault();
                moveTo({rowIndex: rowIndex - PAGE_ROWS, columnId});
                break;
            case 'PageDown':
                e.preventDefault();
                moveTo({rowIndex: rowIndex + PAGE_ROWS, columnId});
                break;
            case 'Enter':
            case 'F2':
                if (startEditing(focused)) {
                    e.preventDefault();
                }
                break;
            case ' ':
                e.preventDefault();
                rows[rowIndex]?.toggleSelected();
                break;
            default: {
                const kind = table.getColumn(columnId)?.columnDef.meta?.editor;
                if (
                    e.key.length === 1 &&
                    !e.ctrlKey &&
                    !e.metaKey &&
                    !e.altKey &&
                    (kind === 'text' || kind === 'emoji') &&
                    startEditing(focused, e.key)
                ) {
                    e.preventDefault();
                }
            }
        }
    };

    const cellId = (rowIndex: number, columnId: string) =>
        `${gridId}-${rowIndex}-${columnId}`;

    const headerGroups = table.getHeaderGroups();
    const totalWidth = table.getTotalSize();
    const virtualItems = virtualizer.getVirtualItems();
    const focusedRow = focused ? rows[focused.rowIndex] : undefined;

    return (
        <div
            ref={setScrollRef}
            role="grid"
            tabIndex={0}
            aria-rowcount={rows.length}
            aria-activedescendant={
                focused && focusedRow && !editing
                    ? cellId(focused.rowIndex, focused.columnId)
                    : undefined
            }
            data-testid={testId}
            onKeyDown={onKeyDown}
            className={cn(
                'relative overflow-auto rounded-md border text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/60',
                className
            )}
        >
            <div style={{minWidth: totalWidth}} className="min-w-full">
                {headerGroups.map(group => (
                    <div
                        key={group.id}
                        role="row"
                        className="sticky top-0 z-10 flex border-b bg-background text-xs font-medium text-muted-foreground"
                    >
                        {group.headers.map(header => {
                            const column = header.column;
                            const meta = column.columnDef.meta;
                            const sorted = column.getIsSorted();

                            return (
                                <div
                                    key={header.id}
                                    role="columnheader"
                                    aria-sort={
                                        sorted === 'asc'
                                            ? 'ascending'
                                            : sorted === 'desc'
                                              ? 'descending'
                                              : undefined
                                    }
                                    data-testid={`col-${column.id}`}
                                    style={{width: header.getSize()}}
                                    className={cn(
                                        'relative flex shrink-0 flex-col justify-center gap-1 px-2 py-1.5',
                                        meta?.align && alignClass[meta.align]
                                    )}
                                >
                                    {header.isPlaceholder ? null : (
                                        <div
                                            className={cn(
                                                'flex min-w-0 items-center gap-1',
                                                meta?.align &&
                                                    alignClass[meta.align]
                                            )}
                                        >
                                            {column.getCanSort() ? (
                                                <button
                                                    type="button"
                                                    onClick={column.getToggleSortingHandler()}
                                                    title={t(
                                                        'table.sort',
                                                        'Sort'
                                                    )}
                                                    className="flex min-w-0 items-center gap-1 rounded-sm outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60"
                                                >
                                                    <span className="truncate">
                                                        {flexRender(
                                                            column.columnDef
                                                                .header,
                                                            header.getContext()
                                                        )}
                                                    </span>
                                                    <SortIcon sorted={sorted} />
                                                </button>
                                            ) : (
                                                <span className="flex min-w-0 items-center gap-1 truncate">
                                                    {flexRender(
                                                        column.columnDef.header,
                                                        header.getContext()
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                    {meta?.filter === 'text' ? (
                                        <HeaderFilter
                                            column={column}
                                            placeholder={meta.filterPlaceholder}
                                        />
                                    ) : null}
                                    {column.getCanResize() ? (
                                        <div
                                            role="separator"
                                            aria-orientation="vertical"
                                            onMouseDown={header.getResizeHandler()}
                                            onTouchStart={header.getResizeHandler()}
                                            onDoubleClick={() =>
                                                column.resetSize()
                                            }
                                            className={cn(
                                                'absolute top-0 right-0 h-full w-1 cursor-col-resize touch-none select-none hover:bg-border',
                                                column.getIsResizing() &&
                                                    'bg-primary'
                                            )}
                                        />
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                ))}
                <div
                    role="rowgroup"
                    className="relative"
                    style={{height: virtualizer.getTotalSize()}}
                >
                    {virtualItems.map(item => {
                        const row = rows[item.index];
                        if (!row) {
                            return null;
                        }

                        return (
                            <div
                                key={item.key}
                                ref={virtualizer.measureElement}
                                data-index={item.index}
                                role="row"
                                aria-rowindex={item.index + 1}
                                aria-selected={row.getIsSelected()}
                                className={cn(
                                    'absolute top-0 left-0 flex w-full border-b hover:bg-accent/40',
                                    row.getIsSelected() && 'bg-primary/5',
                                    rowClassName?.(row.original)
                                )}
                                style={{
                                    transform: `translateY(${item.start}px)`,
                                    minHeight: estimateRowHeight,
                                }}
                            >
                                {row.getVisibleCells().map(cell => {
                                    const columnId = cell.column.id;
                                    const meta = cell.column.columnDef.meta;
                                    const isFocused =
                                        focused?.rowIndex === item.index &&
                                        focused.columnId === columnId;
                                    const isEditing =
                                        editing?.rowIndex === item.index &&
                                        editing.columnId === columnId;
                                    const pos = {
                                        rowIndex: item.index,
                                        columnId,
                                    };

                                    return (
                                        <div
                                            key={cell.id}
                                            id={cellId(item.index, columnId)}
                                            role="gridcell"
                                            aria-selected={
                                                meta?.skipFocus
                                                    ? undefined
                                                    : isFocused
                                            }
                                            data-focused={
                                                isFocused || undefined
                                            }
                                            data-editing={
                                                isEditing || undefined
                                            }
                                            data-column={columnId}
                                            style={{
                                                width: cell.column.getSize(),
                                            }}
                                            onClick={() => {
                                                if (!meta?.skipFocus) {
                                                    setFocused(pos);
                                                    if (!isEditing) {
                                                        focusGrid();
                                                    }
                                                }
                                            }}
                                            onDoubleClick={() => {
                                                if (
                                                    !meta?.skipFocus &&
                                                    !isEditing
                                                ) {
                                                    startEditing(pos);
                                                }
                                            }}
                                            className={cn(
                                                'flex min-w-0 shrink-0 items-center overflow-hidden px-2 py-1',
                                                meta?.align &&
                                                    alignClass[meta.align],
                                                isFocused &&
                                                    'ring-2 ring-ring/60 ring-inset',
                                                isEditing && 'px-0.5',
                                                meta?.className
                                            )}
                                        >
                                            {isEditing &&
                                            renderEditor &&
                                            meta?.editor
                                                ? renderEditor({
                                                      row: row.original,
                                                      columnId,
                                                      kind: meta.editor,
                                                      options: meta.options,
                                                      value: valueOf(
                                                          row,
                                                          columnId
                                                      ),
                                                      initialValue:
                                                          editing?.initialValue,
                                                      onCommit: value =>
                                                          commit(pos, value),
                                                      onCancel: cancel,
                                                  })
                                                : flexRender(
                                                      cell.column.columnDef
                                                          .cell,
                                                      cell.getContext()
                                                  )}
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
                {rows.length === 0 ? (
                    <div className="flex justify-center py-8 text-muted-foreground">
                        {loading ? <InlineLoader /> : emptyLabel}
                    </div>
                ) : null}
                {loadingMore ? (
                    <div className="flex justify-center py-2">
                        <InlineLoader />
                    </div>
                ) : null}
                <div ref={sentinelRef} className="h-px" />
            </div>
        </div>
    );
}

function SortIcon({sorted}: {sorted: false | 'asc' | 'desc'}) {
    if (sorted === 'asc') {
        return <ArrowUpIcon className="size-3 shrink-0" />;
    }
    if (sorted === 'desc') {
        return <ArrowDownIcon className="size-3 shrink-0" />;
    }

    return <ArrowUpDownIcon className="size-3 shrink-0 opacity-40" />;
}

function HeaderFilter<T>({
    column,
    placeholder,
}: {
    column: Column<T, unknown>;
    placeholder?: string;
}) {
    const value = (column.getFilterValue() as string | undefined) ?? '';

    return (
        <FilterInput
            value={value}
            onValueChange={v => column.setFilterValue(v || undefined)}
            placeholder={placeholder}
            className="font-normal"
            data-testid={`filter-${column.id}`}
        />
    );
}
