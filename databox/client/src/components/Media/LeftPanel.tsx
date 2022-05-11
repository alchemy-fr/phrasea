import React, {useState} from "react";
import Facets from "./Asset/Facets";
import CollectionsPanel from "./CollectionsPanel";
import {Box, Tab, Tabs} from "@mui/material";
import {createStyles, makeStyles, styled, Theme, withStyles} from "@mui/material/styles";
import {TabPanelProps} from "@mui/lab";

type TabValue = "facets" | "tree";

function a11yProps(name: TabValue) {
    return {
        value: name,
        index: `tab-${name}`,
        'aria-controls': `tabpanel-${name}`,
    };
}

function TabPanel(props: { index: string } & TabPanelProps) {
    const {children, value, index} = props;

    return (
        <div
            role="tabpanel"
            hidden={value !== index}
            id={`simple-tabpanel-${index}`}
            aria-labelledby={`simple-tab-${index}`}
        >
            {value === index && children}
        </div>
    );
}


const AntTabs = styled(Tabs)({
    root: {
        backgroundColor: 'none',
        borderBottom: '1px solid #e8e8e8',
    },
    indicator: {
        backgroundColor: '#1890ff',
    },
});

const AntTab = styled(Tab)({
    root: {
        '&:hover': {
            color: '#40a9ff',
            opacity: 1,
        },
        '&$selected': {
            color: '#1890ff',
        },
        '&:focus': {
            color: '#40a9ff',
        },
    },
    selected: {},
})

export default function LeftPanel() {
    const [t, setTab] = useState<TabValue>('tree');

    const handleChange = (event: React.ChangeEvent<{}>, newValue: TabValue) => {
        setTab(newValue);
    };

    return <>
        <AntTabs
            value={t}
            onChange={handleChange}
            aria-label="Views"
        >
            <AntTab label="Tree" {...a11yProps('tree')} />
            <AntTab label="Facets" {...a11yProps('facets')} />
        </AntTabs>
        <TabPanel value={t} index={'tree'}>
            <CollectionsPanel/>
        </TabPanel>
        <TabPanel value={t} index={'facets'}>
            <Facets/>
        </TabPanel>
    </>
}
