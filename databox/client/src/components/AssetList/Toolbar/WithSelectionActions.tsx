import React, {useMemo} from 'react';
import {Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import ShareIcon from '@mui/icons-material/Share';
import DeleteAssetsConfirmDialog from '../../Media/Asset/Actions/DeleteAssetsConfirmDialog.tsx';
import {Asset, AssetOrAssetContainer} from '../../../types';
import ExportAssetsDialog from '../../Media/Asset/Actions/ExportAssetsDialog';
import GroupButton, {GroupButtonAction} from '../../Ui/GroupButton';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import EditIcon from '@mui/icons-material/Edit';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import MoveAssetsDialog from '../../Media/Asset/Actions/MoveAssetsDialog';
import CopyAssetsDialog from '../../Media/Asset/Actions/CopyAssetsDialog';
import TextSnippetIcon from '@mui/icons-material/TextSnippet';
import {useModals} from '@alchemy/navigation';
import {useNavigateToModal} from '../../Routing/ModalLink';
import {modalRoutes} from '../../../routes';
import BasketSwitcher from '../../Basket/BasketSwitcher';
import DeleteForeverIcon from '@mui/icons-material/DeleteForever';
import {useAuth} from '@alchemy/react-auth';
import ShareAssetDialog from '../../Share/ShareAssetDialog.tsx';
import {toast} from 'react-toastify';
import RestoreFromTrashIcon from '@mui/icons-material/RestoreFromTrash';
import RestoreAssetsConfirm from '../../Media/Asset/Actions/RestoreAssetsConfirm.tsx';
import {
    ParentSelectionContext,
    SelectionActionsProps,
} from './SelectionActions.tsx';

type Props<Item extends AssetOrAssetContainer> = {
    selectionContext: ParentSelectionContext<Item>;
} & Pick<SelectionActionsProps<Item>, 'reload' | 'actionsContext'>;

export default function WithSelectionActions<
    Item extends AssetOrAssetContainer,
>({reload, actionsContext, selectionContext}: Props<Item>) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
    const {openModal} = useModals();
    const {isAuthenticated} = useAuth();
    const {selection, setSelection, itemToAsset} = selectionContext;

    const {
        canDelete,
        canDeletePermanent,
        canRestore,
        canDownload,
        canEdit,
        canEditAttributes,
        canMove,
        canShare,
        onShare,
        onDelete,
        onDeletePermanent,
        onRestore,
        onCopy,
        onMove,
        onEdit,
        onEditAttributes,
        download,
    } = useMemo(() => {
        let canDelete = false;
        let canDeletePermanent = false;
        let canRestore = false;
        let canDownload = false;
        let canEdit = false;
        let canEditAttributes = false;
        let canMove = false;
        let canShare = false;
        let wsId: string | undefined = undefined;

        function filterEditableAttributes(asset: Asset): boolean {
            return asset.capabilities.canEditAttributes;
        }

        const selectedAssets = itemToAsset
            ? selection.map(itemToAsset)
            : (selection as unknown as Asset[]);

        selectedAssets.forEach((a: Asset) => {
            wsId = a.workspace?.id;
            if (a.main?.file?.url) {
                canDownload = true;
            }
            if (
                !a.deleted &&
                (a.capabilities.canDelete ||
                    (a.collections && a.collections.length > 0))
            ) {
                canDelete = true;
            }
            if (a.capabilities.canDelete) {
                canDeletePermanent = true;
            }
            if (a.capabilities.canDelete && a.deleted) {
                canRestore = true;
            }
            if (a.capabilities.canEdit) {
                canEdit = true;
                canMove = true;
            }
            if (a.capabilities.canEditAttributes) {
                canEditAttributes = true;
            }
            if (a.capabilities.canShare) {
                canShare = true;
            }
            if (a.capabilities.canShare) {
                canShare = true;
            }
        });

        for (const a of selectedAssets) {
            if (wsId && a.workspace?.id !== wsId) {
                canEditAttributes = canMove = false;
                break;
            }
        }

        const onMove = () => {
            openModal(MoveAssetsDialog, {
                assetIds: selectedAssets.map(i => i.id),
                workspaceId: wsId!,
                onComplete: () => {
                    reload?.();
                },
            });
        };

        const onShare = () => {
            if (selectedAssets.length !== 1) {
                toast.warn(
                    t(
                        'asset_actions.share_multiple',
                        'You can only share one asset at a time'
                    )
                );
                return;
            }
            openModal(ShareAssetDialog, {
                asset: selectedAssets[0],
            });
        };

        const onEdit = () => {
            if (selection.length === 1) {
                navigateToModal(modalRoutes.assets.routes.manage, {
                    tab: 'edit',
                    id: selectedAssets[0].id,
                });
            } else {
                navigateToModal(
                    modalRoutes.attributesBatchEdit,
                    {},
                    {
                        state: {
                            selection: selectedAssets
                                .filter(filterEditableAttributes)
                                .map(a => a.id),
                            workspaceId: selectedAssets[0].workspace.id,
                        },
                    }
                );
            }
        };

        const onEditAttributes = () => {
            if (selection.length === 1) {
                navigateToModal(modalRoutes.assets.routes.manage, {
                    tab: 'attributes',
                    id: selectedAssets[0].id,
                });
            } else {
                navigateToModal(
                    modalRoutes.attributesBatchEdit,
                    {},
                    {
                        state: {
                            selection: selectedAssets
                                .filter(filterEditableAttributes)
                                .map(a => a.id),
                            workspaceId: selectedAssets[0].workspace.id,
                        },
                    }
                );
            }
        };

        const download = canDownload
            ? () => {
                  openModal(ExportAssetsDialog, {
                      assets: selectedAssets,
                  });
              }
            : undefined;

        return {
            canDelete,
            canDeletePermanent,
            canRestore,
            canDownload,
            canEdit,
            canEditAttributes,
            canMove,
            canShare,
            onShare,
            onDelete: () => {
                openModal(DeleteAssetsConfirmDialog, {
                    assetIds: selectedAssets.map(i => i.id),
                    onDelete: () => {
                        reload?.();
                    },
                });
            },
            onDeletePermanent: () => {
                openModal(DeleteAssetsConfirmDialog, {
                    hardDelete: true,
                    assetIds: selectedAssets.map(i => i.id),
                    onDelete: () => {
                        reload?.();
                    },
                });
            },
            onRestore: () => {
                openModal(RestoreAssetsConfirm, {
                    assets: selectedAssets,
                    onRestore: () => {
                        reload?.();
                    },
                });
            },
            onCopy: () => {
                openModal(CopyAssetsDialog, {
                    assets: selectedAssets,
                    onComplete: () => {
                        reload?.();
                    },
                });
            },
            onMove,
            onEdit,
            onEditAttributes,
            download,
            wsId,
        };
    }, [selection]);

    const deleteButtonProps: GroupButtonAction = {
        id: 'delete',
        disabled: !canDelete,
        label: t('asset_actions.delete', 'Delete'),
        onClick: onDelete,
        startIcon: <DeleteForeverIcon />,
    };
    const restoreButtonProps: GroupButtonAction = {
        id: 'restore',
        disabled: !canRestore,
        label: t('asset_actions.restore', 'Restore'),
        onClick: onRestore,
        startIcon: <RestoreFromTrashIcon />,
    };
    const mainDeleteAction: GroupButtonAction | undefined =
        actionsContext.delete && !canRestore
            ? deleteButtonProps
            : actionsContext.restore
              ? restoreButtonProps
              : undefined;
    const deleteExtraActions: GroupButtonAction[] = [];
    if (mainDeleteAction) {
        if (
            mainDeleteAction !== deleteButtonProps &&
            actionsContext.delete &&
            canDelete
        ) {
            deleteExtraActions.push(deleteButtonProps);
        }
        if (
            mainDeleteAction !== restoreButtonProps &&
            actionsContext.restore &&
            canRestore
        ) {
            deleteExtraActions.push(restoreButtonProps);
        }
        if (actionsContext.delete && canDeletePermanent) {
            deleteExtraActions.push({
                id: 'delete_permanent',
                label: t(
                    'asset_actions.delete_permanently',
                    'Delete Permanently'
                ),
                onClick: onDeletePermanent,
                startIcon: <DeleteForeverIcon />,
            });
        }
    }

    return (
        <>
            {actionsContext.basket && isAuthenticated ? (
                <BasketSwitcher selectionContext={selectionContext} />
            ) : (
                ''
            )}
            {actionsContext.export ? (
                <Button
                    disabled={!canDownload}
                    variant={'contained'}
                    onClick={download}
                    startIcon={<FileDownloadIcon />}
                >
                    {t('asset_actions.export', 'Export')}
                </Button>
            ) : (
                ''
            )}
            {actionsContext.edit ? (
                <GroupButton
                    id={'edit'}
                    onClick={onEdit}
                    startIcon={<EditIcon />}
                    disabled={
                        !canEdit || (selection.length > 0 && !canEditAttributes)
                    }
                    actions={[
                        {
                            id: 'move',
                            label: t('asset_actions.move', 'Move'),
                            onClick: onMove,
                            disabled: !canMove,
                            startIcon: <DriveFileMoveIcon />,
                        },
                        {
                            id: 'edit_attrs',
                            label: t(
                                'asset_actions.edit_attributes',
                                'Edit attributes'
                            ),
                            onClick: onEditAttributes,
                            disabled: !canEditAttributes,
                            startIcon: <TextSnippetIcon />,
                        },
                        {
                            id: 'copy',
                            label: t('asset_actions.copy', 'Copy'),
                            onClick: onCopy,
                            disabled: !canShare,
                            startIcon: <FileCopyIcon />,
                        },
                    ]}
                >
                    {t('asset_actions.edit', 'Edit')}
                </GroupButton>
            ) : (
                ''
            )}
            {actionsContext.share ? (
                <Button
                    disabled={!canShare}
                    variant={'contained'}
                    startIcon={<ShareIcon />}
                    onClick={onShare}
                >
                    {t('asset_actions.share', 'Share')}
                </Button>
            ) : (
                ''
            )}

            {mainDeleteAction ? (
                <GroupButton
                    id={'delete'}
                    color={'error'}
                    onClick={mainDeleteAction!.onClick}
                    startIcon={mainDeleteAction!.startIcon}
                    disabled={
                        selection.length === 0 || mainDeleteAction!.disabled
                    }
                    actions={deleteExtraActions}
                >
                    {mainDeleteAction!.label}
                </GroupButton>
            ) : null}

            {actionsContext.extraActions?.map(a => {
                return (
                    <Button
                        key={a.name}
                        startIcon={a.icon}
                        color={a.color}
                        {...(a.buttonProps ?? {})}
                        disabled={selection.length === 0 || a.disabled}
                        onClick={async () => {
                            await a.apply(selection);
                            if (a.reload && reload) {
                                reload();
                            }
                            if (a.resetSelection) {
                                setSelection([]);
                            }
                        }}
                    >
                        {a.labels.multi}
                    </Button>
                );
            })}
        </>
    );
}
