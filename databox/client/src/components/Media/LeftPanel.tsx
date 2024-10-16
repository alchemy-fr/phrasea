import React, {useState} from 'react';
import Facets from './Asset/Facets';
import CollectionsPanel from './CollectionsPanel';
import {Tab, Tabs} from '@mui/material';
import {TabPanelProps} from '@mui/lab';
import BasketsPanel from '../Basket/BasketsPanel';
import {useAuth} from '@alchemy/react-auth';
import {useTranslation} from 'react-i18next';

enum TabEnum {
    facets = 'facets',
    tree = 'tree',
    baskets = 'baskets',
}

function a11yProps(name: TabEnum) {
    return {
        'value': name,
        'index': `tab-${name}`,
        'aria-controls': `tabpanel-${name}`,
    };
}

function TabPanel(props: { index: string } & TabPanelProps) {
    const {children, value, index} = props;

    return (
        <div
            role="tabpanel"
            hidden={value !== index}
            id={`tabpanel-${index}`}
            aria-labelledby={`tab-${index}`}
        >
            {children}
        </div>
    );
}

export default function LeftPanel() {
    const {t} = useTranslation();
    const [tab, setTab] = useState<TabEnum>(TabEnum.facets);
    const {isAuthenticated} = useAuth();
    const treeLoadedOnce = React.useRef(false);

    const handleChange = (_event: React.ChangeEvent<{}>, newValue: TabEnum) => {
        if (newValue === TabEnum.tree && !treeLoadedOnce.current) {
            treeLoadedOnce.current = true;
        }
        setTab(newValue);
    };

    return (
        <>
            <Tabs value={tab} onChange={handleChange} aria-label="Views">
                <Tab
                    label={t('left_panel.facets', `Facets`)}
                    {...a11yProps(TabEnum.facets)}
                />
                <Tab
                    label={t('left_panel.tree', `Tree`)}
                    {...a11yProps(TabEnum.tree)}
                />
                {isAuthenticated() ? (
                    <Tab
                        label={t('left_panel.baskets', `Baskets`)}
                        {...a11yProps(TabEnum.baskets)}
                    />
                ) : null}
            </Tabs>
            <TabPanel value={tab} index={TabEnum.facets}>
                <Facets/>
            </TabPanel>
            <TabPanel value={tab} index={TabEnum.tree}>
                {treeLoadedOnce.current || tab === TabEnum.tree ? <CollectionsPanel/> : ''}
            </TabPanel>
            {isAuthenticated() ? (
                <TabPanel value={tab} index={TabEnum.baskets}>
                    <BasketsPanel/>
                </TabPanel>
            ) : null}
        </>
    );
}
