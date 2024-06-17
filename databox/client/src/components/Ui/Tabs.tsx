import {Tab, Tabs as BaseTabs} from "@mui/material";
import React from "react";
import {TabItem} from "../Dialog/Tabbed/tabTypes.ts";

type Props<P extends Record<string, any>> = {
    tabs: TabItem<P>[];
    currentTabId: string | undefined;
    onTabChange: (tabId: string) => void;
    onNoTab?: () => void;
} & P;

export default function Tabs<P extends Record<string, any>>({
    tabs: configTabs,
    onTabChange,
    currentTabId,
    onNoTab,
    ...rest
}: Props<P>) {
    const tabs = configTabs.filter(t => t.enabled ?? true);
    const tabIndex = tabs.findIndex(t => t.id === currentTabId);
    const currentTab = tabIndex >= 0 ? tabs[tabIndex] : undefined;

    React.useEffect(() => {
        if (!currentTab && onNoTab) {
            onNoTab();
        }
    }, [currentTab, onNoTab]);

    const handleChange = (_event: React.SyntheticEvent, newValue: number) => {
        if (tabs[newValue].component) {
            onTabChange(tabs[newValue].id);
        }
    };

    return <>
        <BaseTabs
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
        </BaseTabs>
        {currentTab && currentTab.component
            ? React.createElement(currentTab.component, {
                ...rest,
                ...currentTab.props,
            })
            : ''}
    </>
}
