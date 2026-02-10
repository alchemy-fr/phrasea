import {mergeAttributes, Node} from '@tiptap/core';
import {ReactNodeViewRenderer} from '@tiptap/react';
import Widget from './Widget.tsx';
import './style.scss';

export interface WidgetOptions<T extends {}> {
    widget: string;
    options: T;
}

type SetWidgetOptions<T extends {}> = {
    widget: string;
    options?: T;
};

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        widget: {
            setWidget: (props: SetWidgetOptions<any>) => ReturnType;
        };
    }
}

export const WidgetExtension = Node.create<WidgetOptions<any>>({
    name: 'widget',

    addOptions() {
        return {
            widget: '',
            options: {},
        };
    },

    group: 'block',
    content: 'inline*',
    atom: true,

    draggable: true,

    addAttributes() {
        return {
            type: {
                default: null,
            },
            options: {
                default: this.options.options,
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'widget',
            },
        ];
    },

    addCommands() {
        return {
            setWidget:
                ({widget, options}: SetWidgetOptions<any>) =>
                ({commands}) => {
                    return commands.insertContent({
                        type: this.name,
                        attrs: {
                            type: widget,
                            options,
                        },
                    });
                },
        };
    },

    renderHTML({HTMLAttributes}) {
        return ['widget', mergeAttributes(HTMLAttributes)];
    },

    addNodeView() {
        // @ts-expect-error unknown options at this level
        return ReactNodeViewRenderer(Widget);
    },
});
