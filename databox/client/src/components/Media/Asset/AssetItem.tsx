import React, {Component, MouseEvent, RefObject} from "react";
import {Asset, Collection} from "../../../types";
import {Badge} from "react-bootstrap";
import apiClient from "../../../api/api-client";
import {CloudDownload, Delete, Edit, Link as LinkIcon} from '@material-ui/icons';
import {ImageListItem, ImageListItemBar, IconButton, ListItemIcon, ListItemText, Menu, MenuItem} from "@material-ui/core";
import InfoIcon from '@material-ui/icons/Info';
import EditAsset from "./EditAsset";
import Icon from "../../ui/Icon";
import {ReactComponent as FolderImg} from '../../../images/icons/folder.svg';
import {ConnectDragSource, DragSource, DragSourceSpec} from 'react-dnd'
import {draggableTypes} from "../draggableTypes";
import AssetPreviewWrapper from "./AssetPreviewWrapper";
import EditAssetAttributes from "./EditAssetAttributes";
import {replaceHighlight} from "./Attribute/Attributes";

export interface DragSourceProps {
    connectDragSource: ConnectDragSource
    isDragging: boolean
}

export type AssetDragProps = {
    '@id': string;
    id: string;
    collectionIds: string[];
}

type Props = {
    selected?: boolean;
    displayAttributes: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
} & Asset;

type AllProps = Props & DragSourceProps;

export const privacyIndices = [
    'Secret',
    'Private in workspace',
    'Public in workspace',
    'Private',
    'Public for users',
    'Public',
];

type State = {
    editing: boolean;
    editingAttributes: boolean;
    menuOpen: boolean;
    hover: boolean;
}

class AssetItem extends Component<AllProps, State> {
    private readonly ref: RefObject<HTMLDivElement>;

    state: State = {
        editing: false,
        editingAttributes: false,
        menuOpen: false,
        hover: false,
    };

    constructor(props: AllProps) {
        super(props);

        this.ref = React.createRef<HTMLDivElement>();
    }

    shouldComponentUpdate(nextProps: Readonly<AllProps>, nextState: Readonly<State>, nextContext: any): boolean {
        return this.state.editing !== nextState.editing
            || this.state.editingAttributes !== nextState.editingAttributes
            || this.state.menuOpen !== nextState.menuOpen
            || this.state.hover !== nextState.hover
            || this.props.selected !== nextProps.selected
            ;
    }

    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props.id, e);
    }

    edit = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editing: true, menuOpen: false});
    }

    editAttributes = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editingAttributes: true, menuOpen: false});
    }

    closeEdit = () => {
        this.setState({editing: false, editingAttributes: false});
    }

    delete = (e: MouseEvent): void => {
        e.stopPropagation();
        if (window.confirm(`Delete? Really?`)) {
            apiClient.delete(`/assets/${this.props.id}`);
            this.setState({menuOpen: false});
        }
    }

    openMenu = () => {
        this.setState({menuOpen: true});
    }

    closeMenu = () => {
        this.setState({menuOpen: false});
    }

    onMouseEnter = () => {
        this.setState({hover: true});
    }

    onMouseLeave = () => {
        this.setState({hover: false});
    }

    download = () => {
        document.location.href = this.props.original!.url!;
    }

    openUrl = (url: string) => {
        document.location.href = url;
    }

    render() {
        const {
            id,
            resolvedTitle,
            titleHighlight,
            description,
            workspace,
            tags,
            original,
            thumbnail,
            thumbnailActive,
            privacy,
            selected,
            collections,
            capabilities,
            connectDragSource,
            isDragging,
        } = this.props;

        const privacyLabel = privacyIndices[privacy];

        let image = 'https://user-images.githubusercontent.com/194400/49531010-48dad180-f8b1-11e8-8d89-1e61320e1d82.png';
        if (thumbnail?.url) {
            image = thumbnail.url;
        }

        const opacity = isDragging ? 0.4 : 1;

        const titleNode = replaceHighlight(titleHighlight  || resolvedTitle);

        return connectDragSource(
            <div
                style={{opacity}}
                onMouseEnter={this.onMouseEnter}
                onMouseLeave={this.onMouseLeave}
            >
                <AssetPreviewWrapper
                    displayAttributes={this.props.displayAttributes}
                    asset={this.props}
                >
                    <ImageListItem
                        onClick={this.onClick}
                        className={`asset-item ${selected ? 'selected' : ''}`}
                    >
                        <img src={thumbnailActive && this.state.hover ? thumbnailActive.url : image} alt={resolvedTitle}/>
                        <ImageListItemBar
                            title={titleNode}
                            subtitle={<div>
                                <div>
                                    {tags.map(t => <Badge
                                        variant={'success'}
                                        key={t.id}
                                    >{t.name}</Badge>)}
                                    <Badge
                                        variant={'secondary'}
                                    >{privacyLabel}</Badge>
                                    <Badge
                                        variant={'primary'}
                                    >{workspace.name}</Badge>
                                </div>
                                <div className={'a-desc'}>{description}</div>
                                <ul className={'a-colls'}>
                                    {this.renderCollections(collections)}
                                </ul>
                            </div>}
                            actionIcon={(original || capabilities.canEdit || capabilities.canDelete) ?
                                <div
                                    ref={this.ref}
                                >
                                    <IconButton
                                        color={'secondary'}
                                        aria-controls={`item-menu-${id}`}
                                        aria-haspopup="true"
                                        onClick={this.openMenu}
                                    >
                                        <InfoIcon/>
                                    </IconButton>
                                </div> : undefined
                            }
                        />
                        {this.state.menuOpen && <Menu
                            id={`item-menu-${id}`}
                            key={`item-menu-${id}`}
                            keepMounted
                            anchorEl={this.ref.current}
                            open={this.state.menuOpen}
                            onClose={this.closeMenu}
                        >
                            {original?.alternateUrls && <>
                                {original.alternateUrls.map(a => <MenuItem
                                    key={a.type}
                                    onClick={() => this.openUrl(a.url)}>
                                    <ListItemIcon>
                                        <LinkIcon fontSize="small"/>
                                    </ListItemIcon>
                                    <ListItemText primary={a.label || a.type}/>
                                </MenuItem>)}
                            </>}
                            {original?.url && <MenuItem onClick={this.download}>
                                <ListItemIcon>
                                    <CloudDownload fontSize="small"/>
                                </ListItemIcon>
                                <ListItemText primary="Download"/>
                            </MenuItem>}
                            {capabilities.canEdit && <MenuItem onClick={this.edit}>
                                <ListItemIcon>
                                    <Edit fontSize="small"/>
                                </ListItemIcon>
                                <ListItemText primary="Edit"/>
                            </MenuItem>}
                            {capabilities.canEdit && <MenuItem onClick={this.editAttributes}>
                                <ListItemIcon>
                                    <Edit fontSize="small"/>
                                </ListItemIcon>
                                <ListItemText primary="Edit attributes"/>
                            </MenuItem>}
                            {capabilities.canDelete && <MenuItem onClick={this.delete}>
                                <ListItemIcon>
                                    <Delete fontSize="small"/>
                                </ListItemIcon>
                                <ListItemText primary="Delete"/>
                            </MenuItem>}
                        </Menu>}
                        {this.state.editing ? <EditAsset
                            id={this.props.id}
                            onClose={this.closeEdit}
                        /> : ''}
                        {this.state.editingAttributes ? <EditAssetAttributes
                            id={this.props.id}
                            workspaceId={this.props.workspace.id}
                            onClose={this.closeEdit}
                        /> : ''}
                    </ImageListItem>
                </AssetPreviewWrapper>
            </div>
        )
    }

    renderCollections(collections: Collection[]) {
        if (collections.length === 0) {
            return null;
        }

        const r = (c: Collection) => <li
            key={c.id}
        >
            <Icon
                variant={'xs'}
                component={FolderImg}/>
            {c.title}
        </li>;

        if (collections.length <= 2) {
            return collections.slice(0, 2).map(r)
        }

        return <>
            {r(collections[0])}
            <li
                title={collections.slice(1).map(c => c.title).join("\n")}
            >
                <Icon
                    variant={'xs'}
                    component={FolderImg}/>
                {`+ ${collections.length - 1} other${collections.length - 1 > 1 ? 's' : ''}`}
            </li>
        </>
    }
}

const itemSource: DragSourceSpec<Props> = {
    canDrag(props) {
        return props.capabilities.canEdit;
    },

    beginDrag(props: Props): AssetDragProps {
        const collectionIds = props.collections.map(c => c.id);

        return {
            '@id': props['@id'],
            id: props.id,
            collectionIds,
        };
    },
}

export default DragSource<Props>(draggableTypes.ASSET, itemSource, (connect, monitor): DragSourceProps => {
    return {
        // Call this function inside render()
        // to let React DnD handle the drag events:
        connectDragSource: connect.dragSource(),
        // You can ask the monitor about the current drag state:
        isDragging: monitor.isDragging(),
    }
})(AssetItem);
