import {useContext} from 'react';
import {
    ClickAwayListener,
    Divider,
    ListItemIcon,
    ListItemText,
    Menu,
    MenuItem,
} from '@mui/material';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../types';
import LinkIcon from '@mui/icons-material/Link';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import {PopoverPosition} from '@mui/material/Popover/Popover';
import DeleteAssetsConfirm from '../Media/Asset/Actions/DeleteAssetsConfirm';
import {ResultContext} from '../Media/Search/ResultContext';
import ExportAssetsDialog from '../Media/Asset/Actions/ExportAssetsDialog';
import FileOpenIcon from '@mui/icons-material/FileOpen';
import {useModals} from '@alchemy/navigation';
import SaveAsButton from '../Media/Asset/Actions/SaveAsButton';
import {useNavigateToModal} from '../Routing/ModalLink';
import SaveIcon from '@mui/icons-material/Save';
import {modalRoutes} from '../../routes';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {ActionsContext, ReloadFunc} from './types.ts';

type Props<Item extends AssetOrAssetContainer> = {
    anchorPosition: PopoverPosition;
    anchorEl: HTMLElement | undefined;
    asset: Asset;
    item: Item;
    onClose: () => void;
    actionsContext: ActionsContext<Item>;
    reload?: ReloadFunc;
    setSelection?: StateSetter<Item[]>;
};

export default function AssetContextMenu<Item extends AssetOrAssetContainer>({
    asset,
    item,
    anchorPosition,
    anchorEl,
    onClose,
    actionsContext,
    reload,
    setSelection,
}: Props<Item>) {
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const resultContext = useContext(ResultContext);
    const {id, original, capabilities} = asset;

    const onDelete = () => {
        openModal(DeleteAssetsConfirm, {
            assetIds: [id],
            onDelete: () => {
                resultContext.reload();
            },
        });
        onClose();
    };

    const onDownload = () => {
        openModal(ExportAssetsDialog, {
            assets: [asset],
        });
        onClose();
    };

    const onOpen = (renditionId: string) => {
        navigateToModal(modalRoutes.assets.routes.view, {
            id: asset.id,
            renditionId,
        });
        onClose();
    };

    const onEdit = () => {
        navigateToModal(modalRoutes.assets.routes.manage, {
            tab: 'edit',
            id: asset.id,
        });
        onClose();
    };

    const onEditAttr = () => {
        navigateToModal(modalRoutes.assets.routes.manage, {
            tab: 'attributes',
            id: asset.id,
        });
        onClose();
    };

    const openUrl = (url: string) => {
        document.location.href = url;
    };

    return (
        <ClickAwayListener onClickAway={onClose} mouseEvent={'onMouseDown'}>
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
                {actionsContext.open && original && (
                    <MenuItem onClick={() => onOpen(original.id)}>
                        <ListItemIcon>
                            <FileOpenIcon />
                        </ListItemIcon>
                        <ListItemText primary="Open" />
                    </MenuItem>
                )}
                {actionsContext.saveAs && asset.source ? (
                    <SaveAsButton
                        Component={MenuItem}
                        asset={asset}
                        file={asset.source}
                        variant={'text'}
                    >
                        <ListItemIcon>
                            <SaveIcon />
                        </ListItemIcon>
                        <ListItemText primary={'Save as'} />

                        <ListItemIcon>
                            <ArrowDropDownIcon />
                        </ListItemIcon>
                    </SaveAsButton>
                ) : (
                    ''
                )}
                {original?.file?.alternateUrls &&
                    original.file.alternateUrls.map(a => (
                        <MenuItem key={a.type} onClick={() => openUrl(a.url)}>
                            <ListItemIcon>
                                <LinkIcon />
                            </ListItemIcon>
                            <ListItemText primary={a.label || a.type} />
                        </MenuItem>
                    ))}
                {original?.file?.url && (
                    <MenuItem onClick={onDownload}>
                        <ListItemIcon>
                            <CloudDownloadIcon />
                        </ListItemIcon>
                        <ListItemText primary="Download" />
                    </MenuItem>
                )}
                {actionsContext.edit ? (
                    <MenuItem
                        disabled={!capabilities.canEdit}
                        onClick={capabilities.canEdit ? onEdit : undefined}
                    >
                        <ListItemIcon>
                            <EditIcon />
                        </ListItemIcon>
                        <ListItemText primary="Edit" />
                    </MenuItem>
                ) : (
                    ''
                )}
                {actionsContext.edit ? (
                    <MenuItem
                        disabled={!capabilities.canEditAttributes}
                        onClick={
                            capabilities.canEditAttributes
                                ? onEditAttr
                                : undefined
                        }
                    >
                        <ListItemIcon>
                            <EditIcon />
                        </ListItemIcon>
                        <ListItemText primary="Edit attributes" />
                    </MenuItem>
                ) : (
                    ''
                )}
                <Divider key={'d'} />
                {actionsContext.delete ? (
                    <MenuItem
                        disabled={!capabilities.canDelete}
                        onClick={capabilities.canDelete ? onDelete : undefined}
                    >
                        <ListItemIcon>
                            <DeleteIcon color={'error'} />
                        </ListItemIcon>
                        <ListItemText primary="Delete" />
                    </MenuItem>
                ) : (
                    ''
                )}
                {actionsContext.extraActions?.map(a => {
                    return (
                        <MenuItem
                            key={a.name}
                            color={a.color}
                            disabled={a.disabled ?? false}
                            onClick={async () => {
                                onClose();
                                await a.apply([item]);
                                if (a.reload && reload) {
                                    reload();
                                }
                                if (a.resetSelection && setSelection) {
                                    setSelection([]);
                                }
                            }}
                        >
                            {a.icon ? (
                                <ListItemIcon>{a.icon}</ListItemIcon>
                            ) : (
                                ''
                            )}
                            {a.labels.single}
                        </MenuItem>
                    );
                })}
            </Menu>
        </ClickAwayListener>
    );
}
