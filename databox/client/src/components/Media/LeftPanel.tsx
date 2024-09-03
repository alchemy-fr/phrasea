import React, {useState} from 'react';
import Facets from './Asset/Facets';
import CollectionsPanel from './CollectionsPanel';
import {Tab, Tabs} from '@mui/material';
import {styled} from '@mui/material/styles';
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

function TabPanel(props: {index: string} & TabPanelProps) {
    const {children, value, index} = props;

    return (
        <div
            role="tabpanel"
            hidden={value !== index}
            id={`simple-tabpanel-${index}`}
            aria-labelledby={`simple-tab-${index}`}
        >
            {children}
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
});

export default function LeftPanel() {
    const {t} = useTranslation();
    const [tab, setTab] = useState<TabEnum>(TabEnum.tree);
    const {isAuthenticated} = useAuth();

    const handleChange = (_event: React.ChangeEvent<{}>, newValue: TabEnum) => {
        setTab(newValue);
    };

    return (
        <>
            <AntTabs value={tab} onChange={handleChange} aria-label="Views">
                <AntTab
                    label={t('left_panel.tree', `Tree`)}
                    {...a11yProps(TabEnum.tree)}
                />
                <AntTab
                    label={t('left_panel.facets', `Facets`)}
                    {...a11yProps(TabEnum.facets)}
                />
                {isAuthenticated() ? (
                    <AntTab
                        label={t('left_panel.baskets', `Baskets`)}
                        {...a11yProps(TabEnum.baskets)}
                    />
                ) : null}
            </AntTabs>
            <TabPanel value={tab} index={TabEnum.tree}>
                <CollectionsPanel />
            </TabPanel>
            <TabPanel value={tab} index={TabEnum.facets}>
                <Facets />
            </TabPanel>
            {isAuthenticated() ? (
                <TabPanel value={tab} index={TabEnum.baskets}>
                    <BasketsPanel />
                </TabPanel>
            ) : null}
        </>
    );
}
