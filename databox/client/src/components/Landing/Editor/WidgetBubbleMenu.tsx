import {Editor} from '@tiptap/core';
import {useEditorState} from '@tiptap/react';
import {widgets} from '../widgets';
import React from 'react';
import {RenderWidgetOptionsProps} from '../widgets/widgetTypes.ts';
import {useTranslation} from 'react-i18next';
import {WidgetOptions} from './extensions/widgets/extension.ts';

type Props = {
    editor: Editor;
};

export default function WidgetBubbleMenu({editor}: Props) {
    const {t} = useTranslation();
    const {widgetAttrs} = useEditorState({
        editor,
        selector: ({editor}) => ({
            widgetAttrs: editor.state.selection?.node?.attrs,
        }),
    });

    if (!widgetAttrs) {
        return null;
    }

    const {type, options} = widgetAttrs as WidgetOptions<any>;

    const widget = widgets.find(w => w.name === type);
    if (!widget) {
        return null;
    }

    const props: RenderWidgetOptionsProps<any> = {
        title: widget.getTitle(t),
        options,
        onRemove: () => {
            editor.chain().focus().deleteSelection().run();
        },
        updateOptions: (newOptions: Partial<any>) => {
            editor
                .chain()
                .focus()
                .setNode('widget', {
                    type,
                    options: {
                        ...options,
                        ...newOptions,
                    },
                })
                .run();
        },
    };

    return React.createElement(widget.optionsComponent, props);
}
