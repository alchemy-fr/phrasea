import {NodeViewWrapper, ReactNodeViewProps} from '@tiptap/react';
import {WidgetOptions} from './extension.ts';
import React from 'react';
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

    return (
        <NodeViewWrapper
            className={classNames({
                widget: true,
                selected,
            })}
            contentEditable={false}
        >
            {React.createElement(widget.component, {
                title: widget.getTitle(t),
                options: attrs.options,
            })}
        </NodeViewWrapper>
    );
}
