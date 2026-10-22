import {beforeAll, describe, expect, it, vi} from 'vitest';
import {type ReactElement, type ReactNode, useState} from 'react';
import {fireEvent, render, screen} from '@testing-library/react';
import type {ColumnDef, SortingState} from '@tanstack/react-table';
import {DataTable} from './DataTable';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/menu';
import type {CellEditorRenderProps} from './types';

// jsdom has no layout: the virtualizer would render no row
vi.mock('@tanstack/react-virtual', () => ({
    useVirtualizer: ({
        count,
        estimateSize,
        getItemKey,
    }: {
        count: number;
        estimateSize: () => number;
        getItemKey: (i: number) => string | number;
    }) => ({
        getVirtualItems: () =>
            Array.from({length: count}, (_, index) => ({
                index,
                key: getItemKey(index),
                start: index * estimateSize(),
            })),
        getTotalSize: () => count * estimateSize(),
        measureElement: () => {},
        scrollToIndex: () => {},
    }),
}));

beforeAll(() => {
    globalThis.IntersectionObserver = class {
        observe() {}
        disconnect() {}
        unobserve() {}
    } as unknown as typeof IntersectionObserver;
    globalThis.ResizeObserver = class {
        observe() {}
        disconnect() {}
        unobserve() {}
    } as unknown as typeof ResizeObserver;
    Element.prototype.scrollTo = () => {};
});

type Item = {id: string; name: string; note: string};

const data: Item[] = [
    {id: '1', name: 'One', note: 'a'},
    {id: '2', name: 'Two', note: 'b'},
    {id: '3', name: 'Three', note: 'c'},
];

const columns: ColumnDef<Item, any>[] = [
    {accessorKey: 'name', header: 'Name', meta: {editor: 'text'}},
    {accessorKey: 'note', header: 'Note', enableSorting: false},
];

function Editor({
    value,
    initialValue,
    onCommit,
    onCancel,
}: CellEditorRenderProps<Item>) {
    return (
        <input
            data-testid="editor"
            autoFocus
            defaultValue={initialValue ?? value}
            onKeyDown={e => {
                if (e.key === 'Enter') {
                    onCommit(e.currentTarget.value);
                } else if (e.key === 'Escape') {
                    onCancel();
                }
            }}
        />
    );
}

function Table({
    onCellCommit = () => {},
    onSortingChange,
    rowWrapper,
}: {
    onCellCommit?: (row: Item, columnId: string, value: string) => void;
    onSortingChange?: (s: SortingState) => void;
    rowWrapper?: (row: Item, rowElement: ReactElement) => ReactNode;
}) {
    const [sorting, setSorting] = useState<SortingState>([]);

    return (
        <DataTable<Item>
            data={data}
            columns={columns}
            getRowId={r => r.id}
            sorting={sorting}
            onSortingChange={updater => {
                const next =
                    typeof updater === 'function' ? updater(sorting) : updater;
                setSorting(next);
                onSortingChange?.(next);
            }}
            onCellCommit={onCellCommit}
            renderEditor={p => <Editor {...p} />}
            rowWrapper={rowWrapper}
            data-testid="grid"
        />
    );
}

const grid = () => screen.getByTestId('grid');
const focusedCell = () =>
    grid().querySelector('[data-focused]') as HTMLElement | null;
const cellOf = (text: string) =>
    screen.getByText(text).closest('[role=gridcell]') as HTMLElement;

describe('DataTable', () => {
    it('moves the focused cell with the arrows and Tab', () => {
        render(<Table />);
        fireEvent.click(cellOf('One'));
        expect(focusedCell()?.textContent).toBe('One');

        fireEvent.keyDown(grid(), {key: 'ArrowDown'});
        expect(focusedCell()?.textContent).toBe('Two');

        fireEvent.keyDown(grid(), {key: 'Tab'});
        expect(focusedCell()?.textContent).toBe('b');

        // Past the last column: first column of the next row
        fireEvent.keyDown(grid(), {key: 'Tab'});
        expect(focusedCell()?.textContent).toBe('Three');

        fireEvent.keyDown(grid(), {key: 'Tab', shiftKey: true});
        expect(focusedCell()?.textContent).toBe('b');

        fireEvent.keyDown(grid(), {key: 'ArrowUp'});
        fireEvent.keyDown(grid(), {key: 'ArrowUp'});
        fireEvent.keyDown(grid(), {key: 'ArrowUp'});
        expect(focusedCell()?.textContent).toBe('a');
    });

    it('edits a cell with Enter and commits the new value', () => {
        const onCellCommit = vi.fn();
        render(<Table onCellCommit={onCellCommit} />);
        fireEvent.click(cellOf('Two'));
        fireEvent.keyDown(grid(), {key: 'Enter'});

        const editor = screen.getByTestId('editor') as HTMLInputElement;
        expect(editor.value).toBe('Two');
        fireEvent.change(editor, {target: {value: 'X'}});
        fireEvent.keyDown(editor, {key: 'Enter'});

        expect(onCellCommit).toHaveBeenCalledWith(data[1], 'name', 'X');
        expect(screen.queryByTestId('editor')).toBeNull();
    });

    it('starts editing with the character typed', () => {
        render(<Table />);
        fireEvent.click(cellOf('One'));
        fireEvent.keyDown(grid(), {key: 'z'});

        expect((screen.getByTestId('editor') as HTMLInputElement).value).toBe(
            'z'
        );
    });

    it('does not commit on Escape', () => {
        const onCellCommit = vi.fn();
        render(<Table onCellCommit={onCellCommit} />);
        fireEvent.doubleClick(cellOf('One'));
        const editor = screen.getByTestId('editor');
        fireEvent.change(editor, {target: {value: 'Changed'}});
        fireEvent.keyDown(editor, {key: 'Escape'});

        expect(screen.queryByTestId('editor')).toBeNull();
        expect(onCellCommit).not.toHaveBeenCalled();
    });

    it('does not commit an unchanged value', () => {
        const onCellCommit = vi.fn();
        render(<Table onCellCommit={onCellCommit} />);
        fireEvent.doubleClick(cellOf('Three'));
        fireEvent.keyDown(screen.getByTestId('editor'), {key: 'Enter'});

        expect(onCellCommit).not.toHaveBeenCalled();
    });

    it('does not edit a column without editor', () => {
        render(<Table />);
        fireEvent.doubleClick(cellOf('a'));

        expect(screen.queryByTestId('editor')).toBeNull();
    });

    it('sorts from the header', () => {
        const onSortingChange = vi.fn();
        render(<Table onSortingChange={onSortingChange} />);
        fireEvent.click(
            screen.getByTestId('col-name').querySelector('button')!
        );

        expect(onSortingChange).toHaveBeenCalledWith([
            {id: 'name', desc: false},
        ]);
        expect(
            screen.getByTestId('col-note').querySelector('button')
        ).toBeNull();
    });

    it('wraps the rows, e.g. in a context menu', () => {
        const onSelect = vi.fn();
        render(
            <Table
                rowWrapper={(item, rowElement) => (
                    <ContextMenu>
                        <ContextMenuTrigger asChild>
                            {rowElement}
                        </ContextMenuTrigger>
                        <ContextMenuContent>
                            <ContextMenuItem onSelect={() => onSelect(item)}>
                                Act on {item.name}
                            </ContextMenuItem>
                        </ContextMenuContent>
                    </ContextMenu>
                )}
            />
        );
        const rows = grid().querySelectorAll<HTMLElement>(
            '[role=rowgroup] > [role=row]'
        );
        expect(rows).toHaveLength(3);
        // The props of the row are kept
        expect(rows[1].style.transform).toBe('translateY(36px)');
        expect(rows[1].getAttribute('data-index')).toBe('1');

        // A right click opens the menu of its row, without editing the cell
        fireEvent.contextMenu(cellOf('Two'));
        expect(screen.queryByTestId('editor')).toBeNull();
        expect(rows[1].getAttribute('data-state')).toBe('open');
        fireEvent.click(screen.getByText('Act on Two'));
        expect(onSelect).toHaveBeenCalledWith(data[1]);
    });
});
