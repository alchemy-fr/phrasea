import React, {useContext} from 'react';
import {ClickAwayListener, Divider, ListItemIcon, ListItemText, Menu, MenuItem} from "@mui/material";
import {Asset} from "../../../types";
import LinkIcon from '@mui/icons-material/Link';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import {PopoverPosition} from "@mui/material/Popover/Popover";
import DeleteAssetsConfirm from "./Actions/DeleteAssetsConfirm";
import {useModals} from "@mattjennings/react-modal-stack";
import {ResultContext} from "../Search/ResultContext";
import ExportAssetsDialog from "./Actions/ExportAssetsDialog";
import EditAsset from "./EditAsset";
import EditAssetAttributes from "./EditAssetAttributes";
import {useModalHash} from "../../../hooks/useModalHash";

type Props = {
    anchorPosition: PopoverPosition;
    anchorEl: HTMLElement | undefined;
    asset: Asset;
    onClose: () => void;
};

export default function AssetContextMenu({
                                             asset,
                                             anchorPosition,
                                             anchorEl,
                                             onClose,
                                         }: Props) {
    const {openModal} = useModalHash();
    const resultContext = useContext(ResultContext);
    const {
        id,
        original,
        capabilities,
    } = asset;

    const onDelete = () => {
        openModal(DeleteAssetsConfirm, {
            assetIds: [id],
            onDelete: () => {
                resultContext.reload();
            }
        });
        onClose();
    };

    const onDownload = () => {
        openModal(ExportAssetsDialog, {
            assets: [asset],
        });
        onClose();
    }

    const onEdit = () => {
        openModal(EditAsset, {
            id,
            onEdit: () => {
                resultContext.reload();
            }
        });
        onClose();
    }

    const onEditAttr = () => {
        openModal(EditAssetAttributes, {
            asset,
            onEdit: () => {
                resultContext.reload();
            },
        });
        onClose();
    }

    return <ClickAwayListener
        onClickAway={onClose}
        mouseEvent={'onMouseDown'}
    >
        <Menu
            id={`item-menu-${id}`}
            key={`item-menu-${id}`}
            keepMounted
            onClose={onClose}
            open={true}
            anchorReference={anchorEl ? 'anchorEl' : 'anchorPosition'}
            anchorEl={anchorEl}
            anchorPosition={anchorPosition}
            style={{
                pointerEvents: 'none',
            }}
            MenuListProps={{
                style: {
                    pointerEvents: 'auto',
                },
            }}
            BackdropProps={{
                invisible: true,
            }}
        >
            {original?.alternateUrls && original.alternateUrls.map(a => <MenuItem
                key={a.type}
                // onClick={() => this.openUrl(a.url)} TODO
            >
                <ListItemIcon>
                    <LinkIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary={a.label || a.type}/>
            </MenuItem>)}
            {original?.url && <MenuItem
                onClick={onDownload}
            >
                <ListItemIcon>
                    <CloudDownloadIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary="Download"/>
            </MenuItem>}
            {capabilities.canEdit && <MenuItem
                onClick={onEdit}
            >
                <ListItemIcon>
                    <EditIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary="Edit"/>
            </MenuItem>}
            {capabilities.canEditAttributes && <MenuItem
                onClick={onEditAttr}
            >
                <ListItemIcon>
                    <EditIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary="Edit attributes"/>
            </MenuItem>}
            {capabilities.canDelete && [
                <Divider key={'d'}/>,
                <MenuItem
                    key={'delete'}
                    onClick={onDelete}
                >
                    <ListItemIcon>
                        <DeleteIcon
                            fontSize="small"
                            color={'error'}
                        />
                    </ListItemIcon>
                    <ListItemText primary="Delete"/>
                </MenuItem>
            ]}
        </Menu>
    </ClickAwayListener>
}
