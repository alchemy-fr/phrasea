import React, {useContext} from 'react';
import {Divider, ListItemIcon, ListItemText, Menu, MenuItem} from "@mui/material";
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

type Props = {
    open: boolean;
    anchorPosition: PopoverPosition;
    asset: Asset;
    onClose: () => void;
};

export default function AssetContextMenu({
                                             asset,
                                             anchorPosition,
                                             open,
                                             onClose,
                                         }: Props) {
    const {openModal} = useModals();
    const resultContext = useContext(ResultContext);
    const {
        id,
        original,
        capabilities,
    } = asset;

    const onDelete = () => {
        openModal(DeleteAssetsConfirm, {
            assetIds: [id],
            count: 1,
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

    return <Menu
        id={`item-menu-${id}`}
        key={`item-menu-${id}`}
        keepMounted
        onClose={onClose}
        open={open}
        anchorReference="anchorPosition"
        anchorPosition={anchorPosition}
        BackdropProps={{
            invisible: true,
            onContextMenu: (e) => {
                e.preventDefault();
                onClose();
            },
        }}
    >
        {original?.alternateUrls && <>
            {original.alternateUrls.map(a => <MenuItem
                key={a.type}
                // onClick={() => this.openUrl(a.url)}
            >
                <ListItemIcon>
                    <LinkIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary={a.label || a.type}/>
            </MenuItem>)}
        </>}
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
        {capabilities.canEdit && <MenuItem
            onClick={onEditAttr}
        >
            <ListItemIcon>
                <EditIcon fontSize="small"/>
            </ListItemIcon>
            <ListItemText primary="Edit attributes"/>
        </MenuItem>}
        {capabilities.canDelete && <>
            <Divider/>
        <MenuItem
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
        </>}
    </Menu>
}
