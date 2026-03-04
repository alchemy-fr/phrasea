import {NodeViewWrapper, ReactNodeViewProps} from '@tiptap/react';
import {WidgetOptions} from './extension.ts';
import React, {useState} from 'react';
import {widgets} from '../../../widgets';
import {useTranslation} from 'react-i18next';
import {Popper, Typography} from '@mui/material';
import classNames from 'classnames';
import './styles.scss';

type Props<T extends {}> = {
    HTMLAttributes: WidgetOptions<T>;
} & ReactNodeViewProps;

export default function Widget<T extends {}>({
    extension: {
        options: {editing},
    },
    node: {attrs},
    HTMLAttributes,
    selected,
    editor,
    updateAttributes,
    deleteNode,
}: Props<T>) {
    const {t} = useTranslation();
    const widget = widgets.find(w => w.name === HTMLAttributes.type);
    const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
    const delay = 800;
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    if (!widget) {
        return (
            <NodeViewWrapper className="widget" contentEditable={false}>
                <Typography>
                    {t('editor.widgets.unknown', 'Unknown widget type')}
                </Typography>
            </NodeViewWrapper>
        );
    }

    const cancelTimeout = () => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
    };
    const triggerClose = () => {
        timeoutRef.current = setTimeout(() => {
            setAnchorEl(null);
        }, delay);
    };

    return (
        <NodeViewWrapper
            className={classNames({
                widget: true,
                selected: selected && editor.isEditable,
            })}
            onMouseEnter={(e: MouseEvent) => {
                cancelTimeout();
                setAnchorEl(e.currentTarget as HTMLElement);
            }}
            onMouseLeave={() => {
                cancelTimeout();
                triggerClose();
            }}
        >
            {editing && (
                <Popper
                    open={Boolean(anchorEl)}
                    anchorEl={anchorEl}
                    placement={'top-start'}
                    sx={theme => ({
                        zIndex: theme.zIndex.modal - 1,
                    })}
                    modifiers={[
                        {
                            name: 'flip',
                            enabled: true,
                            options: {
                                altBoundary: true,
                                rootBoundary: 'document',
                                padding: 8,
                            },
                        },
                        {
                            name: 'preventOverflow',
                            enabled: true,
                            options: {
                                altAxis: true,
                                altBoundary: true,
                                tether: true,
                                rootBoundary: 'document',
                                padding: 8,
                            },
                        },
                    ]}
                >
                    <div
                        onMouseEnter={() => {
                            cancelTimeout();
                        }}
                        onMouseLeave={() => {
                            cancelTimeout();
                            triggerClose();
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
                </Popper>
            )}
            {React.createElement(widget.component, {
                title: widget.getTitle(t),
                options: attrs.options,
            })}
        </NodeViewWrapper>
    );
}
