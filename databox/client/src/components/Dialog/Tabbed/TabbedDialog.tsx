import React, {ReactNode} from 'react';
import {Breakpoint} from '@mui/material';
import {useParams} from '@alchemy/navigation';
import RouteDialog from '../RouteDialog';
import {useCloseModal, useNavigateToModal} from '../../Routing/ModalLink';
import type {RouteDefinition, RouteParameters} from '@alchemy/navigation';
import {AppDialogTitle, BootstrapDialog} from '@alchemy/phrasea-ui';
import {TabItem} from './tabTypes.ts';
import Tabs from '../../Ui/Tabs.tsx';

export type DialogTabProps = {
    onClose: () => void;
    minHeight?: number | undefined;
};

export type DataTabProps<T> = {
    data: T;
    setData?: (data: T) => void;
} & DialogTabProps;

type Props<P extends Record<string, any>> = {
    route: RouteDefinition;
    routeParams?: RouteParameters;
    tabs: TabItem<P>[];
    maxWidth?: Breakpoint | false;
    title?: ReactNode;
} & P;

export default function TabbedDialog<P extends Record<string, any>>({
    route,
    routeParams,
    tabs,
    maxWidth,
    title,
    ...rest
}: Props<P>) {
    const {tab} = useParams();
    const navigateToModal = useNavigateToModal();
    const closeModal = useCloseModal();

    const onChange = React.useCallback((tabId: string) => {
        navigateToModal(route, {
            ...routeParams,
            tab: tabId,
        });
    }, []);

    const onNoTab = React.useCallback(() => {
        closeModal();
    }, [closeModal]);

    return (
        <RouteDialog>
            {({open}) => (
                <BootstrapDialog
                    onClose={closeModal}
                    open={open}
                    fullWidth={true}
                    maxWidth={maxWidth}
                    PaperProps={{
                        style: {
                            minHeight: 550,
                        },
                    }}
                >
                    <AppDialogTitle onClose={closeModal}>
                        {title}
                    </AppDialogTitle>
                    <Tabs<DialogTabProps>
                        tabs={tabs}
                        currentTabId={tab}
                        onTabChange={onChange}
                        onNoTab={onNoTab}
                        onClose={closeModal}
                        minHeight={200}
                        {...rest}
                    />
                </BootstrapDialog>
            )}
        </RouteDialog>
    );
}
