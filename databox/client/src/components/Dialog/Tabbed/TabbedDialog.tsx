import React, {FunctionComponent, ReactNode} from 'react';
import {Breakpoint, Tab, Tabs} from '@mui/material';
import {AppDialogTitle, BootstrapDialog} from '../../Layout/AppDialog';
import {useParams} from '@alchemy/navigation';
import RouteDialog from '../RouteDialog';
import {useCloseModal, useNavigateToModal} from '../../Routing/ModalLink';
import type {RouteDefinition, RouteParameters} from '@alchemy/navigation';

type TabItem<P extends {} = {}, P2 extends {} = any> = {
    title: ReactNode;
    id: string;
    component: FunctionComponent<P2 & P & DialogTabProps>;
    props?: P2 & P;
    enabled?: boolean;
};

export type DialogTabProps = {
    onClose: () => void;
    minHeight?: number | undefined;
};

type Props<P extends {}> = {
    route: RouteDefinition;
    routeParams?: RouteParameters;
    tabs: TabItem<P>[];
    maxWidth?: Breakpoint | false;
    title?: ReactNode;
    minHeight?: number | undefined;
} & P;

export default function TabbedDialog<P extends {}>({
    route,
    routeParams,
    tabs: configTabs,
    maxWidth,
    minHeight,
    title,
    ...rest
}: Props<P>) {
    const {tab} = useParams();
    const navigateToModal = useNavigateToModal();
    const closeModal = useCloseModal();
    const tabs = configTabs.filter(t => t.enabled);
    const tabIndex = tabs.findIndex(t => t.id === tab);
    const currentTab = tabIndex >= 0 ? tabs[tabIndex] : undefined;

    const handleChange = (_event: React.SyntheticEvent, newValue: number) => {
        navigateToModal(route, {
            ...routeParams,
            tab: tabs[newValue].id,
        });
    };

    React.useEffect(() => {
        if (!currentTab) {
            closeModal();
        }
    }, [currentTab, closeModal]);

    return (
        <RouteDialog>
            {({open}) => (
                <BootstrapDialog
                    onClose={closeModal}
                    open={open}
                    fullWidth={true}
                    maxWidth={maxWidth}
                >
                    <AppDialogTitle onClose={closeModal}>
                        {title}
                    </AppDialogTitle>
                    <Tabs
                        variant="scrollable"
                        scrollButtons="auto"
                        value={tabIndex}
                        onChange={handleChange}
                        aria-label="Dialog menu"
                    >
                        {tabs.map(t => {
                            return (
                                <Tab
                                    label={t.title}
                                    id={t.id}
                                    key={t.id}
                                    aria-controls={`tabpanel-${t.id}`}
                                />
                            );
                        })}
                    </Tabs>
                    {currentTab && React.createElement(currentTab.component, {
                        ...rest,
                        ...currentTab.props,
                        onClose: closeModal,
                        minHeight,
                    })}
                </BootstrapDialog>
            )}
        </RouteDialog>
    );
}
