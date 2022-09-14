import React, {FunctionComponent, ReactNode, useState} from "react";
import {Tab, Tabs} from "@mui/material";
import {AppDialogTitle, BootstrapDialog} from "../../Layout/AppDialog";
import {Breakpoint} from "@mui/system";
import {useLocation, useNavigate, useParams} from "react-router-dom";
import {getPath, RouteParams} from "../../../routes";
import RouteDialog from "../RouteDialog";

type TabItem<P extends {} = {}, P2 extends {} = any> = {
    title: ReactNode;
    id: string;
    component: FunctionComponent<P2 & P & DialogTabProps>;
    props?: P2 & P;
    enabled?: boolean;
}

type Props<P extends {}> = {
    routeName: string;
    routeParams?: RouteParams;
    tabs: TabItem<P>[];
    maxWidth?: Breakpoint | false;
    title?: ReactNode;
    minHeight?: number | undefined;
} & P;

export type DialogTabProps = {
    onClose: () => void;
    minHeight?: number | undefined;
}

export default function TabbedDialog<P extends {}>({
                                                       routeName,
                                                       routeParams,
                                                       tabs: configTabs,
                                                       maxWidth,
                                                       minHeight,
                                                       title,
                                                       ...rest
                                                   }: Props<P>) {
    const {tab} = useParams();
    const {state} = useLocation() as {
        state?: {
            background?: string;
        }
    };
    const navigate = useNavigate();
    const tabs = configTabs.filter(t => t.enabled);
    const tabIndex = tabs.findIndex(t => t.id === tab);
    const currentTab = tabs[tabIndex];

    const handleChange = (event: React.SyntheticEvent, newValue: number) => {
        navigate(getPath(routeName, {
            ...routeParams,
            tab: tabs[newValue].id,
        }), {
            state,
        });
    };

    return <RouteDialog>
        {({open, onClose}) => <BootstrapDialog
            onClose={onClose}
            open={open}
            fullWidth={true}
            maxWidth={maxWidth}
        >
            <AppDialogTitle onClose={onClose}>
                {title}
            </AppDialogTitle>
            <Tabs
                variant="scrollable"
                scrollButtons="auto"
                value={tabIndex}
                onChange={handleChange}
                aria-label="Dialog menu"
            >
                {tabs.map((t) => {
                    return <Tab
                        label={t.title}
                        id={t.id}
                        key={t.id}
                        aria-controls={`tabpanel-${t.id}`}
                    />
                })}
            </Tabs>
            {React.createElement(currentTab.component, {
                ...rest,
                ...currentTab.props,
                onClose,
                minHeight,
            })}
        </BootstrapDialog>}
    </RouteDialog>
}
