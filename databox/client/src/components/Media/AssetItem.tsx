import React, {MouseEvent, PureComponent, RefObject} from "react";
import {Asset} from "../../types";
import {Badge} from "react-bootstrap";
import apiClient from "../../api/api-client";
import {Delete, Edit} from '@material-ui/icons';
import {GridListTile, GridListTileBar, IconButton, ListItemIcon, ListItemText, Menu, MenuItem} from "@material-ui/core";
import InfoIcon from '@material-ui/icons/Info';
import EditAsset from "./Asset/EditAsset";
import Icon from "../ui/Icon";
import {ReactComponent as FolderImg} from '../../images/icons/folder.svg';
import {ConnectDragSource, DragSource, DragSourceSpec} from 'react-dnd'
import {draggableTypes} from "./draggableTypes";

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
    menuOpen: boolean;
}

class AssetItem extends PureComponent<AllProps, State> {
    private readonly ref: RefObject<HTMLDivElement>;

    state: State = {
        editing: false,
        menuOpen: false,
    };

    constructor(props: AllProps) {
        super(props);

        this.ref = React.createRef<HTMLDivElement>();
    }


    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props.id, e);
    }

    edit = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editing: true, menuOpen: false});
    }

    closeEdit = () => {
        this.setState({editing: false});
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

    render() {
        const {
            id,
            title,
            description,
            tags,
            preview,
            privacy,
            selected,
            collections,
            capabilities,
            connectDragSource,
            isDragging,
        } = this.props;

        const privacyLabel = privacyIndices[privacy];

        let image = 'https://user-images.githubusercontent.com/194400/49531010-48dad180-f8b1-11e8-8d89-1e61320e1d82.png';
        if (preview) {
            image = preview.url;
        }

        const opacity = isDragging ? 0.4 : 1;

        return connectDragSource(<div
            role="Box"
            style={{ opacity }}
        >
            <GridListTile
                onClick={this.onClick}
                className={`asset-item ${selected ? 'selected' : ''}`}
            >
                <img src={image} alt={title}/>
                <GridListTileBar
                    title={title}
                    subtitle={<div>
                        <div>
                            {tags.map(t => <Badge
                                variant={'success'}
                                key={t.id}
                            >{t.name}</Badge>)}
                            <Badge
                                variant={'secondary'}
                            >{privacyLabel}</Badge>
                        </div>
                        <div className={'a-desc'}>{description}</div>
                        <ul className={'a-colls'}>
                            {collections.slice(0, 1).map(c => <li
                                key={c.id}
                            >
                                <Icon
                                    variant={'xs'}
                                    component={FolderImg}/>
                                {c.title}
                            </li>)}
                            {collections.length > 1 && <li
                                title={collections.slice(1).map(c => c.title).join("\n")}
                            >
                                <Icon
                                    variant={'xs'}
                                    component={FolderImg}/>
                                {`+ ${collections.length - 1} other${collections.length - 1 > 1 ? 's' : ''}`}
                            </li>}
                        </ul>
                    </div>}
                    actionIcon={(capabilities.canEdit || capabilities.canDelete) ?
                        <div
                            ref={this.ref}
                        >
                            <IconButton
                                aria-controls={`item-menu-${id}`}
                                aria-haspopup="true"
                                onClick={this.openMenu}
                            >
                                <InfoIcon/>
                            </IconButton>
                        </div> : undefined
                    }
                />
                <Menu
                    id={`item-menu-${id}`}
                    keepMounted
                    anchorEl={this.ref.current}
                    open={this.state.menuOpen}
                    onClose={this.closeMenu}
                >
                    {capabilities.canEdit && <MenuItem onClick={this.edit}>
                        <ListItemIcon>
                            <Edit fontSize="small"/>
                        </ListItemIcon>
                        <ListItemText primary="Edit"/>
                    </MenuItem>}
                    {capabilities.canDelete && <MenuItem onClick={this.delete}>
                        <ListItemIcon>
                            <Delete fontSize="small"/>
                        </ListItemIcon>
                        <ListItemText primary="Delete"/>
                    </MenuItem>}
                </Menu>
                {this.state.editing ? <EditAsset
                    id={this.props.id}
                    onClose={this.closeEdit}
                /> : ''}
            </GridListTile>
        </div>)
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
