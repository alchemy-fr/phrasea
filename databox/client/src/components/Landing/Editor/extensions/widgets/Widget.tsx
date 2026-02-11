import {NodeViewWrapper, ReactNodeViewProps} from '@tiptap/react';
import {WidgetOptions} from './extension.ts';
import React from 'react';
import {widgets} from '../../../widgets';
import {RenderWidgetProps} from '../../../widgets/widgetTypes.ts';
import {useTranslation} from 'react-i18next';
import {Typography} from '@mui/material';

type Props<T extends {}> = {
    HTMLAttributes: WidgetOptions<T>;
} & ReactNodeViewProps;

export default function Widget<T extends {}>({
    node: {attrs},
    HTMLAttributes,
    selected,
    updateAttributes,
}: Props<T>) {
    const {t} = useTranslation();
    const widget = widgets.find(w => w.name === HTMLAttributes.type);

    if (!widget) {
        return (
            <NodeViewWrapper className="widget" contentEditable={false}>
                <Typography>
                    {t('editor.widgets.unknown', 'Unknown widget type')}
                </Typography>
            </NodeViewWrapper>
        );
    }

    const props: RenderWidgetProps<T> = {
        title: widget.getTitle(t),
        options: attrs.options,
    };

    const updateOptions = (options: Partial<T>) => {
        updateAttributes({
            options: {
                ...attrs.options,
                ...options,
            },
        });
    };

    return (
        <NodeViewWrapper className="widget" contentEditable={false}>
            {selected
                ? React.createElement(widget.optionsComponent, {
                      ...props,
                      updateOptions,
                  })
                : null}

            {React.createElement(widget.component, {
                ...props,
            })}
        </NodeViewWrapper>
    );
}
