import {Editor} from '@tiptap/core';
import {ReactNode} from 'react';

export type EditorMenuAction = ConcreteEditorMenuAction | EditorMenuDivider;

export type ConcreteEditorMenuAction = {
    id: string;
    label: string;
    icon: ReactNode;
    isActive: boolean;
    can: boolean;
    toggle: (editor: Editor) => void;
    isDivider?: never;
};

export type EditorMenuDivider = {
    id: string;
    isDivider: true;
};
export enum TextAlignEnum {
    Left = 'left',
    Center = 'center',
    Right = 'right',
    Justify = 'justify',
}
