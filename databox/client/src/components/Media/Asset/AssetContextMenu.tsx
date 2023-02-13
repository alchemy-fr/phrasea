import React, {useContext} from 'react';
import {ClickAwayListener, Divider, ListItemIcon, ListItemText, Menu, MenuItem} from "@mui/material";
import {Asset} from "../../../types";
import LinkIcon from '@mui/icons-material/Link';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import {PopoverPosition} from "@mui/material/Popover/Popover";
import DeleteAssetsConfirm from "./Actions/DeleteAssetsConfirm";
import {ResultContext} from "../Search/ResultContext";
import ExportAssetsDialog from "./Actions/ExportAssetsDialog";
import {getPath} from "../../../routes";
import {useNavigate} from "react-router-dom";
import FileOpenIcon from '@mui/icons-material/FileOpen';
import {useModals} from "../../../hooks/useModalStack";
import SaveAsButton from "./Actions/SaveAsButton";

type Props = {
    anchorPosition: PopoverPosition;
    anchorEl: HTMLElement | undefined;
    asset: Asset;
    onClose: () => void;
};

export function hasContextMenu({capabilities}: Asset): boolean {
    return capabilities.canEdit
        || capabilities.canEditPermissions
        || capabilities.canEditAttributes;
}

export default function AssetContextMenu({
    asset,
    anchorPosition,
    anchorEl,
    onClose,
}: Props) {
    const {openModal} = useModals();
    const navigate = useNavigate();
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

    const onOpen = (renditionId: string) => {
        navigate(getPath('app_asset_view', {
            assetId: asset.id,
            renditionId
        }));
        onClose();
    }

    const onEdit = () => {
        navigate(getPath('app_asset_manage', {
            tab: 'edit',
            id: asset.id,
        }));
        onClose();
    }

    const onEditAttr = () => {
        navigate(getPath('app_asset_manage', {
            tab: 'attributes',
            id: asset.id,
        }));
        onClose();
    }

    const openUrl = (url: string) => {
        document.location.href = url;
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
            {original && <MenuItem
                onClick={() => onOpen(original.id)}
            >
                <ListItemIcon>
                    <FileOpenIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary="Open"/>
            </MenuItem>}
            {asset.source && <SaveAsButton
                asset={asset}
                file={asset.source}
                variant={'text'}
            />}
            {original?.file?.alternateUrls && original.file.alternateUrls.map(a => <MenuItem
                key={a.type}
                onClick={() => openUrl(a.url)}
            >
                <ListItemIcon>
                    <LinkIcon fontSize="small"/>
                </ListItemIcon>
                <ListItemText primary={a.label || a.type}/>
            </MenuItem>)}
            {original?.file?.url && <MenuItem
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
