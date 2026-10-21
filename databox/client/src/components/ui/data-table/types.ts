import type {ReactNode} from 'react';
import type {RowData} from '@tanstack/react-table';

export type CellEditorKind = 'text' | 'select' | 'color' | 'emoji';

export type CellEditorOption = {value: string; label: ReactNode};

export type EditableColumnMeta<TData> = {
    editor?: CellEditorKind;
    options?: CellEditorOption[];
    /** Defaults to true when `editor` is set */
    editable?: (row: TData) => boolean;
    /** Value handed to the editor; defaults to `String(cell.getValue() ?? '')` */
    getValue?: (row: TData) => string;
    /** Text filter rendered below the header title */
    filter?: 'text';
    filterPlaceholder?: string;
    align?: 'left' | 'right' | 'center';
    /** Not reachable by the keyboard navigation (selection, actions) */
    skipFocus?: boolean;
    className?: string;
};

// The column `meta` of every table: a merged declaration, whose type
// parameters must be the library's ones
/* eslint-disable @typescript-eslint/no-empty-object-type, @typescript-eslint/no-unused-vars */
declare module '@tanstack/react-table' {
    interface ColumnMeta<
        TData extends RowData,
        TValue,
    > extends EditableColumnMeta<TData> {}
}
/* eslint-enable @typescript-eslint/no-empty-object-type, @typescript-eslint/no-unused-vars */

export type CellPosition = {rowIndex: number; columnId: string};

export type CellEditorRenderProps<TData> = {
    row: TData;
    columnId: string;
    kind: CellEditorKind;
    options?: CellEditorOption[];
    value: string;
    /** The character typed to start the edition, replacing the value */
    initialValue?: string;
    onCommit: (value: string) => void;
    onCancel: () => void;
};
