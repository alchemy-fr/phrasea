import {ClickAwayListener, Divider, ListItemIcon, ListItemText, Menu, MenuItem,} from '@mui/material';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../types';
import LinkIcon from '@mui/icons-material/Link';
import {useTranslation} from 'react-i18next';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import {PopoverPosition} from '@mui/material/Popover/Popover';
import FileOpenIcon from '@mui/icons-material/FileOpen';
import SaveAsButton from '../Media/Asset/Actions/SaveAsButton';
import SaveIcon from '@mui/icons-material/Save';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {ActionsContext, ReloadFunc} from './types.ts';
import {useAssetActions} from "../../hooks/useAssetActions.ts";

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
    const {t} = useTranslation();
    const {id, original} = asset;

    const {
        onDelete,
        onOpen,
        onDownload,
        onEdit,
        onEditAttr,
        can,
    } = useAssetActions({asset, onAction: onClose, actionsContext});

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
                {can.open && (
                    <MenuItem onClick={() => onOpen(original!.id)}>
                        <ListItemIcon>
                            <FileOpenIcon/>
                        </ListItemIcon>
                        <ListItemText primary={t(
                            'asset_actions.open',
                            'Open'
                        )}/>
                    </MenuItem>
                )}
                {can.saveAs ? (
                    <SaveAsButton
                        Component={MenuItem}
                        asset={asset}
                        file={asset.source!}
                        variant={'text'}
                    >
                        <ListItemIcon>
                            <SaveIcon/>
                        </ListItemIcon>
                        <ListItemText primary={t('asset_context_menu.save_as', `Save as`)}/>

                        <ListItemIcon>
                            <ArrowDropDownIcon/>
                        </ListItemIcon>
                    </SaveAsButton>
                ) : (
                    ''
                )}
                {original?.file?.alternateUrls &&
                    original.file.alternateUrls.map(a => (
                        <MenuItem key={a.type} onClick={() => openUrl(a.url)}>
                            <ListItemIcon>
                                <LinkIcon/>
                            </ListItemIcon>
                            <ListItemText primary={a.label || a.type}/>
                        </MenuItem>
                    ))}
                {can.download && (
                    <MenuItem onClick={onDownload}>
                        <ListItemIcon>
                            <CloudDownloadIcon/>
                        </ListItemIcon>
                        <ListItemText primary={t(
                            'asset_actions.download',
                            'Download'
                        )}/>
                    </MenuItem>
                )}
                {actionsContext.edit ? (
                    <MenuItem
                        disabled={!can.edit}
                        onClick={can.edit ? onEdit : undefined}
                    >
                        <ListItemIcon>
                            <EditIcon/>
                        </ListItemIcon>
                        <ListItemText primary={t(
                            'asset_actions.edit',
                            'Edit'
                        )}/>
                    </MenuItem>
                ) : (
                    ''
                )}
                {actionsContext.edit ? (
                    <MenuItem
                        disabled={!can.editAttributes}
                        onClick={
                            can.editAttributes
                                ? onEditAttr
                                : undefined
                        }
                    >
                        <ListItemIcon>
                            <EditIcon/>
                        </ListItemIcon>
                        <ListItemText primary={t(
                            'asset_actions.edit_attributes',
                            'Edit attributes'
                        )}/>
                    </MenuItem>
                ) : (
                    ''
                )}
                <Divider key={'d'}/>
                {actionsContext.delete ? (
                    <MenuItem
                        disabled={!can.delete}
                        onClick={can.delete ? onDelete : undefined}
                    >
                        <ListItemIcon>
                            <DeleteIcon color={'error'}/>
                        </ListItemIcon>
                        <ListItemText primary={t('asset_context_menu.delete', `Delete`)}/>
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
