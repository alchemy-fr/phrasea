import {FunctionComponent, ReactNode} from 'react';
import {DialogTabProps} from './TabbedDialog';

interface TabLink {
    component?: never;
}

interface TabComponent<P extends {} = {}, P2 extends {} = any> {
    onClick?: () => void;
    component: FunctionComponent<P2 & P & DialogTabProps>;
}

export type TabItem<P extends {} = {}, P2 extends {} = any> = (
    | TabLink
    | TabComponent<P, P2>
) & {
    title: ReactNode;
    id: string;
    props?: P2 & P;
    enabled?: boolean;
    onClick?: () => void;
};
