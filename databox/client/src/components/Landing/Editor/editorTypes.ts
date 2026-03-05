import {Editor} from '@tiptap/core';
import {ReactNode} from 'react';

export type EditorMenuAction<State extends object> =
    | ConcreteEditorMenuAction
    | EditorMenuDivider
    | EditorMenuComponent<State>;

export type ConcreteEditorMenuAction = {
    id: string;
    label: string;
    icon: ReactNode;
    isActive: boolean;
    can: boolean;
    toggle: (editor: Editor) => void;
    isDivider?: never;
    render?: never;
};

export type EditorMenuDivider = {
    id: string;
    isDivider: true;
    render?: never;
};

export type EditorMenuComponent<State extends object> = {
    id: string;
    isDivider?: never;
    render: (props: {editor: Editor; editorState: State}) => ReactNode;
};

export enum TextAlignEnum {
    Left = 'left',
    Center = 'center',
    Right = 'right',
    Justify = 'justify',
}
