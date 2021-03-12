import React from 'react';
import {makeStyles} from '@material-ui/core/styles';
import List from '@material-ui/core/List';
import ListSubheader from '@material-ui/core/ListSubheader';
import {Workspace} from "../../types";
import CollectionMenuItem from "./CollectionMenuItem";
import {ListItem} from "@material-ui/core";

const useStyles = makeStyles((theme) => ({
    root: {
        width: '100%',
        backgroundColor: theme.palette.background.paper,
        position: 'relative',
        overflow: 'auto',
    },
    listSection: {
        backgroundColor: 'inherit',
    },
    ul: {
        backgroundColor: 'inherit',
        padding: 0,
    },
}));

type Props = {
    workspaces: Workspace[];
    selectedCollection?: string;
    selectedWorkspace?: string;
    onCollectionSelect: Function;
    onWorkspaceSelect: (id: string) => void;
}

export default function WorkspacesMenu(props: Props) {
    const classes = useStyles();

    return (
        <div className={classes.root}>
            <List subheader={<li/>}>
                {props.workspaces.map((w) => (
                    <li key={`section-${w.id}`} className={classes.listSection}>
                        <ul className={classes.ul}>
                            <ListSubheader disableGutters={true}>
                                <ListItem
                                    onClick={() => props.onWorkspaceSelect(w.id)}
                                    button
                                    selected={props.selectedWorkspace === w.id}
                                    className={`workspace-item`}
                                >
                                    {w.name}
                                </ListItem>
                            </ListSubheader>
                            {w.collections.map(c => <CollectionMenuItem
                                {...c}
                                key={c.id}
                                absolutePath={c.id}
                                selectedPath={props.selectedCollection}
                                onClick={props.onCollectionSelect}
                                level={0}
                            />)}
                        </ul>
                    </li>
                ))}
            </List>
        </div>
    );
}
