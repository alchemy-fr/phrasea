import React from 'react';
import {ListItemIcon, ListItemText, Menu, MenuItem} from "@mui/material";
import {Asset} from "../../../types";
import {PopoverProps} from "@mui/material/Popover";
import LinkIcon from '@mui/icons-material/Link';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import {PopoverPosition} from "@mui/material/Popover/Popover";

type Props = {
    open: boolean;
    anchorPosition: PopoverPosition;
    asset: Asset;
    onClose: () => void;
};

export default function AssetContextMenu({
                                             asset: {
                                                 id,
                                                 original,
                                                 capabilities,
                                             },
                                             anchorPosition,
                                             open,
                                             onClose,
                                         }: Props) {
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
            // onClick={this.download}
        >
            <ListItemIcon>
                <CloudDownloadIcon fontSize="small"/>
            </ListItemIcon>
            <ListItemText primary="Download"/>
        </MenuItem>}
        {capabilities.canEdit && <MenuItem
            // onClick={this.edit}
        >
            <ListItemIcon>
                <EditIcon fontSize="small"/>
            </ListItemIcon>
            <ListItemText primary="Edit"/>
        </MenuItem>}
        {capabilities.canEdit && <MenuItem
            // onClick={this.editAttributes}
        >
            <ListItemIcon>
                <EditIcon fontSize="small"/>
            </ListItemIcon>
            <ListItemText primary="Edit attributes"/>
        </MenuItem>}
        {capabilities.canDelete && <MenuItem
            // onClick={this.delete}
        >
            <ListItemIcon>
                <DeleteIcon fontSize="small"/>
            </ListItemIcon>
            <ListItemText primary="Delete"/>
        </MenuItem>}
    </Menu>
}
