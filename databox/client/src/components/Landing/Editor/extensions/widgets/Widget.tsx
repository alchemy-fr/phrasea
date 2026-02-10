import {NodeViewWrapper, ReactNodeViewProps} from '@tiptap/react';
import {WidgetOptions} from './extension.ts';
import React from 'react';
import {widgets} from '../../../widgets';
import {RenderWidgetProps} from '../../../widgets/widgetTypes.ts';

type Props<T extends {}> = {
    HTMLAttributes: WidgetOptions<T>;
} & ReactNodeViewProps;

export default function Widget<T extends {}>({
    node: {attrs},
    HTMLAttributes,
    selected,
    updateAttributes,
}: Props<T>) {
    const widget = widgets.find(w => w.name === HTMLAttributes.type);

    if (!widget) {
        return (
            <NodeViewWrapper className="widget">
                <label contentEditable={false}>{HTMLAttributes.type}</label>
                <p>Widget not found</p>
            </NodeViewWrapper>
        );
    }

    const props: RenderWidgetProps<T> = {
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
        <NodeViewWrapper className="widget">
            <label contentEditable={false}>{HTMLAttributes.type}</label>

            {selected ? (
                <div className={'widget-options'}>
                    {React.createElement(widget.optionsComponent, {
                        ...props,
                        updateOptions,
                    })}
                </div>
            ) : null}

            <div contentEditable={false}>
                {React.createElement(widget.component, {
                    ...props,
                })}
            </div>
        </NodeViewWrapper>
    );
}
