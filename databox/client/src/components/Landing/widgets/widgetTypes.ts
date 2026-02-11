import {TFunction} from '@alchemy/i18n';
import {AppDialogProps} from '@alchemy/phrasea-ui';
import React, {PropsWithChildren, ReactNode} from 'react';

export interface WidgetInterface<T extends {} = {}> {
    getTitle: (t: TFunction) => string;
    name: string;
    component: React.FC<RenderWidgetProps<T>>;
    optionsComponent: React.FC<RenderWidgetOptionsProps<T>>;
}

export type RenderWidgetProps<T extends {} = {}> = {
    title: ReactNode;
    options: T;
};

export type OnWidgetRemove = () => void;

export type RenderWidgetOptionsProps<T extends {}> = {
    updateOptions: (options: Partial<T>) => void;
    onRemove: OnWidgetRemove;
} & RenderWidgetProps<T>;

export type WidgetOptionsContainerProps = PropsWithChildren<{
    onRemove: OnWidgetRemove;
    title: ReactNode;
}>;

export type WidgetOptionsDialogWrapperProps = {
    dialogProps?: Partial<AppDialogProps>;
} & WidgetOptionsContainerProps;
