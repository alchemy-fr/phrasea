import React, {MouseEvent, PureComponent, RefObject} from "react";
import {Asset} from "../../types";
import {Badge} from "react-bootstrap";
import apiClient from "../../api/api-client";
import {Edit, Delete} from '@material-ui/icons';
import {
    GridListTile,
    GridListTileBar,
    IconButton,
    ListItemIcon, ListItemText,
    Menu,
    MenuItem
} from "@material-ui/core";
import InfoIcon from '@material-ui/icons/Info';
import EditAsset from "./Asset/EditAsset";

type Props = {
    selected?: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
} & Asset;

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

export default class AssetItem extends PureComponent<Props, State> {
    private readonly ref: RefObject<HTMLDivElement>;

    state: State = {
        editing: false,
        menuOpen: false,
    };

    constructor(props: Props) {
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
            privacy,
            selected,
            collections,
            capabilities,
        } = this.props;

        const privacyLabel = privacyIndices[privacy];

        let image = 'https://user-images.githubusercontent.com/194400/49531010-48dad180-f8b1-11e8-8d89-1e61320e1d82.png';

        return <GridListTile
            onClick={this.onClick}
            className={`asset-item ${selected ? 'selected' : ''}`}
        >
            <img src={image} alt={title}/>
            <GridListTileBar
                title={title}
                subtitle={<div>
                    <div>{description}</div>
                    {collections.map(c => <div
                        key={c.id}
                    >{c.title}</div>)}
                    <div>
                        {tags.map(t => <Badge
                            variant={'success'}
                            key={t.id}
                        >{t.name}</Badge>)}
                        <Badge
                            variant={'info'}
                        >{privacyLabel}</Badge>
                    </div>
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
    }
}
