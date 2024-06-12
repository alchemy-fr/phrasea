import React, {FunctionComponent, ReactNode} from 'react';
import {Breakpoint, Tab, Tabs} from '@mui/material';
import {useParams} from '@alchemy/navigation';
import RouteDialog from '../RouteDialog';
import {useCloseModal, useNavigateToModal} from '../../Routing/ModalLink';
import type {RouteDefinition, RouteParameters} from '@alchemy/navigation';
import {AppDialogTitle, BootstrapDialog} from '@alchemy/phrasea-ui';

interface TabLink {
    component?: never;
}

interface TabComponent<P extends {} = {}, P2 extends {} = any> {
    onClick?: () => void;
    component: FunctionComponent<P2 & P & DialogTabProps>;
}

type TabItem<P extends {} = {}, P2 extends {} = any> = (
    | TabLink
    | TabComponent<P, P2>
) & {
    title: ReactNode;
    id: string;
    props?: P2 & P;
    enabled?: boolean;
    onClick?: () => void;
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
        if (tabs[newValue].component) {
            navigateToModal(route, {
                ...routeParams,
                tab: tabs[newValue].id,
            });
        }
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
                                    role={'navigation'}
                                    aria-controls={`tabpanel-${t.id}`}
                                    onClick={
                                        t.onClick
                                            ? t.component
                                                ? e => {
                                                      e.preventDefault();
                                                      e.stopPropagation();
                                                      t.onClick!();
                                                  }
                                                : t.onClick
                                            : undefined
                                    }
                                />
                            );
                        })}
                    </Tabs>
                    {currentTab && currentTab.component
                        ? React.createElement(currentTab.component, {
                              ...rest,
                              ...currentTab.props,
                              onClose: closeModal,
                              minHeight,
                          })
                        : ''}
                </BootstrapDialog>
            )}
        </RouteDialog>
    );
}
