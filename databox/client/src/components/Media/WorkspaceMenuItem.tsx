import React, {MouseEvent, Component} from "react";
import {Collection, Workspace} from "../../types";
import CollectionMenuItem from "./CollectionMenuItem";
import EditWorkspace from "./Workspace/EditWorkspace";
import CreateCollection from "./Collection/CreateCollection";
import {IconButton, ListItem, ListItemSecondaryAction} from "@material-ui/core";
import CreateNewFolder from "@material-ui/icons/CreateNewFolder";
import EditIcon from "@material-ui/icons/Edit";
import {ExpandLess, ExpandMore, MoreHoriz} from "@material-ui/icons";
import ListSubheader from "@material-ui/core/ListSubheader";
import {ReactComponent as WorkspaceImg} from "../../images/icons/workspace.svg";
import Icon from "../ui/Icon";
import {collectionChildrenLimit, collectionSecondLimit, getCollections} from "../../api/collection";
import {SearchFiltersContext} from "./Search/SearchFiltersContext";

export type WorkspaceMenuItemProps = {} & Workspace;

type State = {
    expanded: boolean,
    editing: boolean,
    addCollection: boolean,
    loadingMore: boolean,
    nextCollections?: Collection[];
    totalCollections?: number;
}

function propsAreSame(a: Record<string, any>, b: Record<string, any>): boolean {
    return !Object.keys(a).some(k => a[k] !== b[k]);
}

export default class WorkspaceMenuItem extends Component<WorkspaceMenuItemProps, State> {
    static contextType = SearchFiltersContext;
    context: React.ContextType<typeof SearchFiltersContext>;

    state: State = {
        expanded: true,
        editing: false,
        addCollection: false,
        loadingMore: false,
        nextCollections: undefined,
    };

    shouldComponentUpdate(nextProps: Readonly<WorkspaceMenuItemProps>, nextState: Readonly<State>, nextContext: React.ContextType<typeof SearchFiltersContext>): boolean {
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

    getNextPage(): number | undefined {
        const {collections} = this.props;
        const {nextCollections, totalCollections} = this.state;

        if (collections.length >= collectionChildrenLimit) {
            if (nextCollections && totalCollections) {
                if (nextCollections.length < totalCollections) {
                    return Math.floor(nextCollections.length / collectionSecondLimit) + 1;
                }
            } else {
                return 1;
            }
        }
    }

    loadMore = async (e: MouseEvent): Promise<void> => {
        const nextPage = this.getNextPage();
        this.setState({loadingMore: true});

        const nextCollections = await getCollections({
            workspaces: [this.props.id],
            page: nextPage,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });

        this.setState(prevState => ({
            loadingMore: false,
            totalCollections: nextCollections.total,
            nextCollections: (prevState.nextCollections || []).concat(nextCollections.result),
        }));
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
        const {
            editing,
            expanded,
            addCollection,
            nextCollections,
            loadingMore,
        } = this.state;

        const selected = this.context.selectedWorkspace === id;

        const nextPage = this.getNextPage();

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
            {expanded && !nextCollections && collections.map(c => <CollectionMenuItem
                {...c}
                key={c.id}
                absolutePath={c.id}
                level={0}
            />)}
            {expanded && nextCollections && nextCollections.map(c => <CollectionMenuItem
                {...c}
                key={c.id}
                absolutePath={c.id}
                level={0}
            />)}
            {expanded && Boolean(nextPage) && <ListItem
                onClick={this.loadMore}
                disabled={loadingMore}
                button
            >
                <Icon
                    component={MoreHoriz}
                />
                Load more collections
            </ListItem>}
        </>
    }
}
