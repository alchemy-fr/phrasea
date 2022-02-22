import React, {PureComponent, MouseEvent, Component} from "react";
import {Workspace} from "../../types";
import {SelectionContext} from "./SelectionContext";
import CollectionMenuItem from "./CollectionMenuItem";
import EditWorkspace from "./Workspace/EditWorkspace";
import CreateCollection from "./Collection/CreateCollection";
import {IconButton, ListItem, ListItemSecondaryAction} from "@material-ui/core";
import CreateNewFolder from "@material-ui/icons/CreateNewFolder";
import EditIcon from "@material-ui/icons/Edit";
import {ExpandLess, ExpandMore} from "@material-ui/icons";
import ListSubheader from "@material-ui/core/ListSubheader";
import {ReactComponent as WorkspaceImg} from "../../images/icons/workspace.svg";
import Icon from "../ui/Icon";

export type WorkspaceMenuItemProps = {} & Workspace;

type State = {
    expanded: boolean,
    editing: boolean,
    addCollection: boolean,
}

function propsAreSame(a: Record<string, any>, b: Record<string, any>): boolean {
    return !Object.keys(a).some(k => a[k] !== b[k]);
}

export default class WorkspaceMenuItem extends Component<WorkspaceMenuItemProps, State> {
    static contextType = SelectionContext;
    context: React.ContextType<typeof SelectionContext>;

    state: State = {
        expanded: true,
        editing: false,
        addCollection: false,
    };

    shouldComponentUpdate(nextProps: Readonly<WorkspaceMenuItemProps>, nextState: Readonly<State>, nextContext: React.ContextType<typeof SelectionContext>): boolean {
        return !nextContext ||
            (nextContext.selectedWorkspace !== this.context.selectedWorkspace
                || nextContext.selectedCollection !== this.context.selectedCollection
            )
            || propsAreSame(this.state, nextState)
            || propsAreSame(this.props, nextProps)
            ;
    }

    expandWorkspace = async (force = false): Promise<void> => {
        this.setState((prevState: State) => ({
            expanded: !prevState.expanded || force,
        }));
    }

    onClick = (e: MouseEvent): void => {
        this.context.selectWorkspace(this.props.id, this.context.selectedWorkspace === this.props.id);
        this.expandWorkspace(true);
    }

    onExpandClick = (e: MouseEvent) => {
        e.stopPropagation();
        this.expandWorkspace();
    }

    edit = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editing: true});
    }

    closeEdit = () => {
        this.setState({editing: false});
    }

    addCollection = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({addCollection: true});
    }

    closeCollection = () => {
        this.setState({addCollection: false});
    }

    render() {
        const {
            id,
            name,
            capabilities,
            collections,
        } = this.props;
        const {editing, expanded, addCollection} = this.state;

        const selected = this.context.selectedWorkspace === id;

        return <>
            <ListSubheader
                disableGutters={true}
                className={'workspace-item'}
            >
                <ul>
                    <ListItem
                        onClick={this.onClick}
                        selected={selected}
                        button
                    >
                        <Icon
                            component={WorkspaceImg}
                        />
                        {name}
                        <ListItemSecondaryAction>
                            {capabilities.canEdit && <IconButton
                                title={'Add collection in this workspace'}
                                onClick={this.addCollection}
                                className={'c-action'}
                                aria-label="add-child">
                                <CreateNewFolder/>
                            </IconButton>}
                            {capabilities.canEdit && <IconButton
                                title={'Edit this workspace'}
                                onClick={this.edit}
                                className={'c-action'}
                                aria-label="edit">
                                <EditIcon/>
                            </IconButton>}
                            {collections.length > 0 ? <IconButton
                                onClick={this.onExpandClick}
                                aria-label="expand-toggle">
                                {!expanded ? <ExpandLess
                                    onClick={this.onExpandClick}
                                /> : <ExpandMore/>}
                            </IconButton> : ''}
                        </ListItemSecondaryAction>
                    </ListItem>
                </ul>
            </ListSubheader>
            {editing && <EditWorkspace
                id={this.props.id}
                onClose={this.closeEdit}
            />}
            {addCollection && <CreateCollection
                workspaceId={this.props['@id']}
                onClose={this.closeCollection}
            />}
            {expanded && collections.map(c => <CollectionMenuItem
                {...c}
                key={c.id}
                absolutePath={c.id}
                level={0}
            />)}
        </>
    }
}
