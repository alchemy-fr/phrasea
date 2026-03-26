import {Divider, ListItemIcon, ListItemText, MenuItem} from '@mui/material';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../types';
import LinkIcon from '@mui/icons-material/Link';
import {useTranslation} from 'react-i18next';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import FileOpenIcon from '@mui/icons-material/FileOpen';
import SaveAsButton from '../Media/Asset/Actions/SaveAsButton';
import SaveIcon from '@mui/icons-material/Save';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {ActionsContext, ReloadFunc} from './types.ts';
import {useAssetActions} from '../../hooks/useAssetActions.ts';
import ContextMenu from '../Ui/ContextMenu.tsx';
import {ContextMenuContext} from '../../hooks/useContextMenu.ts';
import InfoIcon from '@mui/icons-material/Info';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import ShareIcon from '@mui/icons-material/Share';
import RestoreFromTrashIcon from '@mui/icons-material/RestoreFromTrash';
import React, {ReactNode} from 'react';

type Props<Item extends AssetOrAssetContainer> = {
    contextMenu: ContextMenuContext<{
        asset: Asset;
        item: Item;
    }>;
    onClose: () => void;
    actionsContext: ActionsContext<Item>;
    reload?: ReloadFunc;
    setSelection?: StateSetter<Item[]>;
};

export default function AssetContextMenu<Item extends AssetOrAssetContainer>({
    contextMenu,
    onClose,
    actionsContext,
    reload,
    setSelection,
}: Props<Item>) {
    const {t} = useTranslation();
    const {asset, item} = contextMenu.data;
    const {id, main} = asset;

    const {
        onDelete,
        onRestore,
        onOpen,
        onDownload,
        onInfo,
        onEdit,
        onMove,
        onCopy,
        onReplace,
        onShare,
        can,
    } = useAssetActions({asset, onAction: onClose, actionsContext, reload});

    const openUrl = (url: string) => {
        document.location.href = url;
    };

    const children: ReactNode[] = [];

    if (can.open) {
        children.push(
            <MenuItem key={'open'} onClick={() => onOpen()}>
                <ListItemIcon>
                    <FileOpenIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.open', 'Open')} />
            </MenuItem>
        );
    }

    if (can.saveAs) {
        children.push(
            <SaveAsButton
                key={'save-as'}
                Component={MenuItem}
                asset={asset}
                file={asset.source!}
                variant={'text'}
            >
                <ListItemIcon>
                    <SaveIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.save_as', `Save as`)} />

                <ListItemIcon>
                    <ArrowDropDownIcon />
                </ListItemIcon>
            </SaveAsButton>
        );
    }

    if (main?.file?.alternateUrls) {
        main.file.alternateUrls.forEach(a => {
            children.push(
                <MenuItem key={a.type} onClick={() => openUrl(a.url)}>
                    <ListItemIcon>
                        <LinkIcon />
                    </ListItemIcon>
                    <ListItemText primary={a.label || a.type} />
                </MenuItem>
            );
        });
    }

    if (actionsContext.info) {
        children.push(
            <MenuItem key={'info'} onClick={onInfo}>
                <ListItemIcon>
                    <InfoIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.info', 'Info')} />
            </MenuItem>
        );
    }

    if (can.download) {
        children.push(
            <MenuItem key={'download'} onClick={onDownload}>
                <ListItemIcon>
                    <CloudDownloadIcon />
                </ListItemIcon>
                <ListItemText
                    primary={t('asset.actions.download', 'Download')}
                />
            </MenuItem>
        );
    }

    if (can.share) {
        children.push(
            <MenuItem key={'share'} onClick={onShare}>
                <ListItemIcon>
                    <ShareIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.share', 'Share')} />
            </MenuItem>
        );
    }

    if (actionsContext.edit) {
        children.push(
            <MenuItem
                key={'edit'}
                disabled={!can.edit}
                onClick={can.edit ? onEdit : undefined}
            >
                <ListItemIcon>
                    <EditIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.edit', 'Edit')} />
            </MenuItem>
        );
    }

    if (actionsContext.move) {
        children.push(
            <MenuItem
                key={'move'}
                disabled={!can.edit}
                onClick={can.edit ? onMove : undefined}
            >
                <ListItemIcon>
                    <DriveFileMoveIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.move', 'Move')} />
            </MenuItem>
        );
    }

    if (actionsContext.copy) {
        children.push(
            <MenuItem
                key={'copy'}
                disabled={!can.share}
                onClick={can.share ? onCopy : undefined}
            >
                <ListItemIcon>
                    <FileCopyIcon />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.copy', 'Copy')} />
            </MenuItem>
        );
    }

    if (actionsContext.replace) {
        children.push(
            <MenuItem
                key={'replace'}
                disabled={!can.edit}
                onClick={can.edit ? onReplace : undefined}
            >
                <ListItemIcon>
                    <ChangeCircleIcon />
                </ListItemIcon>
                <ListItemText
                    primary={t(
                        'asset.actions.replace_source_file',
                        'Replace source file'
                    )}
                />
            </MenuItem>
        );
    }

    if (children.length > 0) {
        children.push(<Divider key={'divider'} />);
    }

    if (actionsContext.delete && !can.restore) {
        children.push(
            <MenuItem
                key={'delete'}
                disabled={!can.delete}
                onClick={can.delete ? onDelete : undefined}
            >
                <ListItemIcon>
                    <DeleteIcon color={'error'} />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.delete', `Delete`)} />
            </MenuItem>
        );
    } else if (actionsContext.restore) {
        children.push(
            <MenuItem
                key={'restore'}
                disabled={!can.restore}
                onClick={can.restore ? onRestore : undefined}
            >
                <ListItemIcon>
                    <RestoreFromTrashIcon color={'error'} />
                </ListItemIcon>
                <ListItemText primary={t('asset.actions.restore', `Restore`)} />
            </MenuItem>
        );
    }

    if (actionsContext.extraActions) {
        if (children.length > 0) {
            children.push(<Divider key={'extra-actions-divider'} />);
        }
        actionsContext.extraActions.forEach(a => {
            children.push(
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
                    {a.icon ? <ListItemIcon>{a.icon}</ListItemIcon> : ''}
                    {a.labels.single}
                </MenuItem>
            );
        });
    }

    return (
        <ContextMenu id={id} onClose={onClose} contextMenu={contextMenu}>
            {children}
        </ContextMenu>
    );
}
