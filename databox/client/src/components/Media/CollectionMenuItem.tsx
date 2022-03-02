import React, {PureComponent, MouseEvent} from "react";
import {Collection} from "../../types";
import {
    addAssetToCollection,
    collectionChildrenLimit,
    collectionSecondLimit,
    getCollections
} from "../../api/collection";
import apiClient from "../../api/api-client";
import EditCollection from "./Collection/EditCollection";
import ListItem from "@material-ui/core/ListItem";
import ListItemText from "@material-ui/core/ListItemText";
import {Collapse, IconButton, ListItemSecondaryAction} from "@material-ui/core";
import EditIcon from '@material-ui/icons/Edit';
import CreateNewFolder from '@material-ui/icons/CreateNewFolder';
import AddPhotoAlternate from '@material-ui/icons/AddPhotoAlternate';
import DeleteIcon from '@material-ui/icons/Delete';
import {ExpandLess, ExpandMore, MoreHoriz} from "@material-ui/icons";
import CreateCollection from "./Collection/CreateCollection";
import {SelectionContext} from "./SelectionContext";
import CreateAsset from "./Asset/CreateAsset";
import {ConnectDropTarget, DropTarget, DropTargetMonitor, DropTargetSpec} from 'react-dnd';
import {draggableTypes} from "./draggableTypes";
import classnames from "classnames";
import {AssetDragProps} from "./Asset/AssetItem";
import Icon from "../ui/Icon";

type DropTargetProps = {
    isOver: boolean,
    connectDropTarget: ConnectDropTarget,
    canDrop: boolean,
};

type Props = {
    level: number;
    absolutePath: string,
} & Collection;

type AllProps = Props & DropTargetProps;

type State = {
    totalChildren?: number;
    collections?: Collection[],
    expanded: boolean,
    editing: boolean,
    addSubCollection: boolean,
    addAsset: boolean,
    loadingMore: boolean,
}

class CollectionMenuItem extends PureComponent<AllProps, State> {
    static contextType = SelectionContext;
    context: React.ContextType<typeof SelectionContext>;

    state: State = {
        expanded: false,
        loadingMore: false,
        editing: false,
        addSubCollection: false,
        addAsset: false,
    };

    loadMore = async (e: MouseEvent): Promise<void> => {
        const nextPage = this.getNextPage();
        this.setState({loadingMore: true});

        const nextCollections = await getCollections({
            parent: this.props.id,
            page: nextPage,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });

        this.setState((prevState: State) => ({
            loadingMore: false,
            totalChildren: nextCollections.total,
            collections: (prevState.collections || []).concat(nextCollections.result),
        }));
    }

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
                    limit: collectionSecondLimit,
                    childrenLimit: collectionChildrenLimit,
                }));
                this.setState({
                    collections: data.result,
                    totalChildren: data.total,
                });
            }
        });
    }

    getNextPage(): number | undefined {
        const {collections, totalChildren} = this.state;

        if (collections && totalChildren && collections.length < totalChildren) {
            return Math.floor(collections.length / collectionSecondLimit) + 1;
        }
    }

    onClick = (e: MouseEvent): void => {
        this.context.selectCollection(this.props.absolutePath, this.context.selectedCollection === this.props.absolutePath);
        this.expandCollection(true);
    }

    onExpandClick = (e: MouseEvent) => {
        e.stopPropagation();
        this.expandCollection();
    }

    addSubCollection = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({addSubCollection: true});
    }

    closeSubCollection = () => {
        this.setState({addSubCollection: false});
    }

    addAsset = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({addAsset: true});
    }

    closeAsset = () => {
        this.setState({addAsset: false});
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
            capabilities,
            level,
        } = this.props;
        const {isOver, connectDropTarget, canDrop} = this.props
        const {editing, addSubCollection, expanded, addAsset} = this.state;

        const selected = this.context.selectedCollection === absolutePath;
        const currentInSelectedHierarchy = this.context.selectedCollection && this.context.selectedCollection.startsWith(absolutePath);

        return <>
            <li className={classnames(['collection-item'], {
                'drag-hover': canDrop && isOver,
            })}>
                <ul>
                    {connectDropTarget!(<div>
                        <ListItem
                            button
                            selected={Boolean(selected || currentInSelectedHierarchy)}
                            onClick={this.onClick}
                            style={{paddingLeft: `${10 + level * 10}px`}}
                        >
                            <ListItemText primary={title}/>
                            <ListItemSecondaryAction>
                                {capabilities.canEdit && <IconButton
                                    onClick={this.addAsset}
                                    className={'c-action'}
                                    title={'Add new asset to collection'}
                                    aria-label="create-asset">
                                    <AddPhotoAlternate/>
                                </IconButton>}
                                {capabilities.canEdit && <IconButton
                                    onClick={this.addSubCollection}
                                    className={'c-action'}
                                    title={'Create new collection in this one'}
                                    aria-label="add-child">
                                    <CreateNewFolder/>
                                </IconButton>}
                                {capabilities.canEdit && <IconButton
                                    title={'Edit this collection'}
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
                                    {!expanded ? <ExpandLess/> : <ExpandMore/>}
                                </IconButton> : ''}
                            </ListItemSecondaryAction>
                        </ListItem>
                    </div>)}
                </ul>
            </li>

            <Collapse in={expanded} timeout="auto" unmountOnExit>
                {this.renderChildren()}
            </Collapse>
            {editing ? <EditCollection
                id={this.props.id}
                onClose={this.closeEdit}
            /> : ''}
            {addSubCollection ? <CreateCollection
                parent={this.props['@id']}
                parentTitle={this.props.title}
                onClose={this.closeSubCollection}
            /> : ''}
            {addAsset ? <CreateAsset
                collectionId={this.props['@id']}
                collectionTitle={this.props.title}
                onClose={this.closeAsset}
            /> : ''}
        </>
    }

    renderChildren() {
        const {collections, expanded, loadingMore} = this.state;
        if (!expanded || !collections) {
            return '';
        }

        const nextPage = this.getNextPage();

        return <div className="sub-colls">
            {collections.map(c => <WrappedCollectionMenuItem
                {...c}
                key={c.id}
                absolutePath={`${this.props.absolutePath}/${c.id}`}
                level={this.props.level + 1}
            />)}
            {Boolean(nextPage) && <ListItem
                onClick={this.loadMore}
                disabled={loadingMore}
                button
            >
                <Icon
                    component={MoreHoriz}
                />
                Load more collections
            </ListItem>}
        </div>
    }
}

const itemTarget: DropTargetSpec<Props> = {
    canDrop(props, monitor: DropTargetMonitor<AssetDragProps>) {
        return !monitor.getItem().collectionIds.includes(props.id);
    },

    drop(props, monitor, component) {
        if (monitor.didDrop()) {
            return
        }

        addAssetToCollection(props['@id'], monitor.getItem()['@id']);
    }
}

const WrappedCollectionMenuItem = DropTarget<Props>(draggableTypes.ASSET, itemTarget, (connect, monitor): DropTargetProps => {
    return {
        connectDropTarget: connect.dropTarget(),
        isOver: monitor.isOver(),
        canDrop: monitor.canDrop(),
    }
})(CollectionMenuItem);

export default WrappedCollectionMenuItem;
