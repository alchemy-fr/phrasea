import React, {useState} from "react";
import Facets from "./Asset/Facets";
import CollectionsPanel from "./CollectionsPanel";
import {TabPanelProps} from "@material-ui/lab";
import {Tab, Tabs} from "@mui/material";
import {createStyles, makeStyles, Theme, withStyles} from "@material-ui/core/styles";

type TabValue = "facets" | "tree";

function a11yProps(name: TabValue) {
    return {
        value: name,
        index: `tab-${name}`,
        'aria-controls': `tabpanel-${name}`,
    };
}

interface StyledTabProps {
    label: string;
}

const useStyles = makeStyles((theme: Theme) => ({
    root: {
        flexGrow: 1,
        backgroundColor: 'none',
    },
    paper: {
        backgroundColor: '#FFF',
    }
}));

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


const AntTabs = withStyles({
    root: {
        backgroundColor: 'none',
        borderBottom: '1px solid #e8e8e8',
    },
    indicator: {
        backgroundColor: '#1890ff',
    },
})(Tabs);

const AntTab = withStyles((theme: Theme) =>
    createStyles({
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
    }),
)((props: StyledTabProps) => <Tab disableRipple {...props} />);


export default function LeftPanel() {
    const classes = useStyles();
    const [t, setTab] = useState<TabValue>('tree');

    const handleChange = (event: React.ChangeEvent<{}>, newValue: TabValue) => {
        setTab(newValue);
    };

    return <div className={classes.root}>
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
    </div>
}
