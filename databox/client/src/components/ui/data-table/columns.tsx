import type {ReactNode} from 'react';
import type {ColumnDef} from '@tanstack/react-table';
import {Checkbox} from '@/components/ui/controls';

export function selectionColumn<T>(labels: {
    selectAll: string;
    selectRow: string;
}): ColumnDef<T, unknown> {
    return {
        id: 'select',
        size: 36,
        enableSorting: false,
        enableResizing: false,
        meta: {skipFocus: true, align: 'center'},
        header: ({table}) => (
            <Checkbox
                aria-label={labels.selectAll}
                checked={
                    table.getIsAllRowsSelected()
                        ? true
                        : table.getIsSomeRowsSelected()
                          ? 'indeterminate'
                          : false
                }
                onCheckedChange={v => table.toggleAllRowsSelected(v === true)}
            />
        ),
        cell: ({row}) => (
            <Checkbox
                aria-label={labels.selectRow}
                data-testid="row-select"
                checked={row.getIsSelected()}
                disabled={!row.getCanSelect()}
                onCheckedChange={v => row.toggleSelected(v === true)}
            />
        ),
    };
}

export function actionsColumn<T>(
    render: (row: T) => ReactNode,
    size = 120
): ColumnDef<T, unknown> {
    return {
        id: 'actions',
        size,
        enableSorting: false,
        enableResizing: false,
        meta: {skipFocus: true, align: 'right'},
        header: () => null,
        cell: ({row}) => render(row.original),
    };
}
