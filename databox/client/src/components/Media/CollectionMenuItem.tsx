import React, {PureComponent, MouseEvent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import apiClient from "../../api/api-client";
import EditCollection from "./Collection/EditCollection";
import ListItem from "@material-ui/core/ListItem";
import ListItemText from "@material-ui/core/ListItemText";
import {Collapse, IconButton, ListItemSecondaryAction} from "@material-ui/core";
import EditIcon from '@material-ui/icons/Edit';
import DeleteIcon from '@material-ui/icons/Delete';
import {ExpandLess, ExpandMore} from "@material-ui/icons";

type State = {
    collections?: Collection[],
    expanded: boolean,
    editing: boolean,
}

export type CollectionMenuItemProps = {
    level: number;
    onClick: Function,
    absolutePath: string,
    selectedPath?: string,
} & Collection;

export default class CollectionMenuItem extends PureComponent<CollectionMenuItemProps, State> {
    state: State = {
        expanded: false,
        editing: false,
    };

    expandCollection = async (force = false): Promise<void> => {
        const {children} = this.props;

        this.setState((prevState: State) => {
            return {
                expanded: !prevState.expanded || force,
            };
        }, async (): Promise<void> => {
            if (this.state.expanded && children && children.length > 0) {
                const data = (await getCollections({
                    parent: this.props.id,
                })).result;
                this.setState({collections: data});
            }
        });
    }

    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props, e);

        this.expandCollection(true);
    }

    onExpandClick = (e: MouseEvent) => {
        e.stopPropagation();
        this.expandCollection();
    }

    edit = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editing: true});
    }

    closeEdit = () => {
        this.setState({editing: false});
    }

    delete = (e: MouseEvent): void => {
        e.stopPropagation();
        if (window.confirm(`Delete? Really?`)) {
            apiClient.delete(`/collections/${this.props.id}`);
        }
    }

    render() {
        const {
            title,
            children,
            absolutePath,
            selectedPath,
            capabilities,
            level,
        } = this.props;
        const {editing, expanded} = this.state;

        const selected = selectedPath === absolutePath;
        const currentInSelectedHierarchy = selectedPath && selectedPath.startsWith(absolutePath);

        return <div
            className={'collection-item'}
        >
            <ListItem
                button
                selected={Boolean(selected || currentInSelectedHierarchy)}
                onClick={this.onClick}
                style={{paddingLeft: `${10 + level * 10}px`}}
            >
                <ListItemText primary={title}/>
                <ListItemSecondaryAction>
                    {capabilities.canEdit && <IconButton
                        onClick={this.edit}
                        className={'c-action'}
                        aria-label="edit">
                        <EditIcon/>
                    </IconButton>}
                    {capabilities.canDelete && <IconButton
                        onClick={this.delete}
                        className={'c-action'}
                        aria-label="delete">
                        <DeleteIcon/>
                    </IconButton>}
                    {children && children.length > 0 ? <IconButton
                        onClick={this.onExpandClick}
                        aria-label="expand-toggle">
                        {!expanded ? <ExpandLess
                            onClick={this.onExpandClick}
                        /> : <ExpandMore/>}
                    </IconButton> : ''}
                </ListItemSecondaryAction>
            </ListItem>

            <Collapse in={expanded} timeout="auto" unmountOnExit>
                {this.renderChildren()}
            </Collapse>
            {editing ? <EditCollection
                id={this.props.id}
                onClose={this.closeEdit}
            /> : ''}
        </div>
    }

    renderChildren() {
        const {collections, expanded} = this.state;
        if (!expanded || !collections) {
            return '';
        }

        return <div className="sub-colls">
            {collections.map(c => <CollectionMenuItem
                {...c}
                key={c.id}
                absolutePath={`${this.props.absolutePath}/${c.id}`}
                selectedPath={this.props.selectedPath}
                onClick={this.props.onClick}
                level={this.props.level + 1}
            />)}
        </div>
    }
}
