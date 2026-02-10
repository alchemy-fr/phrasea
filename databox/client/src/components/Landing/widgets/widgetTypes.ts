import {TFunction} from '@alchemy/i18n';
import React from 'react';

export interface WidgetInterface<T extends {} = {}> {
    getTitle: (t: TFunction) => string;
    name: string;
    component: React.FC<RenderWidgetProps<T>>;
    optionsComponent: React.FC<RenderWidgetOptionsProps<T>>;
}

export type RenderWidgetProps<T extends {} = {}> = {
    options: T;
};

export type RenderWidgetOptionsProps<T extends {}> = {
    updateOptions: (options: Partial<T>) => void;
} & RenderWidgetProps<T>;
