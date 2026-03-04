import {NodeViewWrapper, ReactNodeViewProps} from '@tiptap/react';
import {WidgetOptions} from './extension.ts';
import React, {useState} from 'react';
import {widgets} from '../../../widgets';
import {useTranslation} from 'react-i18next';
import {Typography} from '@mui/material';
import classNames from 'classnames';
import './styles.scss';

type Props<T extends {}> = {
    HTMLAttributes: WidgetOptions<T>;
} & ReactNodeViewProps;

export default function Widget<T extends {}>({
    node: {attrs},
    HTMLAttributes,
    selected,
    editor,
    updateAttributes,
    deleteNode,
}: Props<T>) {
    const {t} = useTranslation();
    const [hover, setHover] = useState(false);
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

    return (
        <NodeViewWrapper
            className={classNames({
                widget: true,
                selected: selected && editor.isEditable,
            })}
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
        >
            {hover && (
                <div
                    style={{
                        position: 'relative',
                    }}
                >
                    <div
                        style={{
                            position: 'absolute',
                            top: -56,
                            left: 0,
                            zIndex: 10,
                        }}
                    >
                        {React.createElement(widget.optionsComponent, {
                            title: widget.getTitle(t),
                            options: attrs.options,
                            updateOptions: (options: Partial<T>) => {
                                updateAttributes({
                                    options: {
                                        ...attrs.options,
                                        ...options,
                                    },
                                });
                            },
                            onRemove: () => {
                                deleteNode();
                            },
                        })}
                    </div>
                </div>
            )}
            {React.createElement(widget.component, {
                title: widget.getTitle(t),
                options: attrs.options,
            })}
        </NodeViewWrapper>
    );
}
