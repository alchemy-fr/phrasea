import React, {Context, useCallback, useContext, useMemo} from 'react';
import {
    Badge,
    Box,
    Button,
    Checkbox,
    Divider,
    Paper,
    ToggleButtonGroup,
    Tooltip,
} from '@mui/material';
import {Trans, useTranslation} from 'react-i18next';
import GridViewIcon from '@mui/icons-material/GridView';
import ViewListIcon from '@mui/icons-material/ViewList';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import ShareIcon from '@mui/icons-material/Share';
import TooltipToggleButton from '../../Ui/TooltipToggleButton';
import {TSelectionContext} from '../../../context/AssetSelectionContext';
import {styled} from '@mui/material/styles';
import DeleteAssetsConfirmDialog from '../../Media/Asset/Actions/DeleteAssetsConfirmDialog.tsx';
import DisplayOptionsMenu from './DisplayOptionsMenu';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../../types';
import ExportAssetsDialog from '../../Media/Asset/Actions/ExportAssetsDialog';
import GroupButton from '../../Ui/GroupButton';
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
import {Layout} from '../Layouts';
import {ActionsContext, ReloadFunc} from '../types';
import DeleteForeverIcon from '@mui/icons-material/DeleteForever';
import {useAuth} from '@alchemy/react-auth';
import ViewQuiltIcon from '@mui/icons-material/ViewQuilt';
import ShareAssetDialog from '../../Share/ShareAssetDialog.tsx';
import {toast} from 'react-toastify';
import AttributeListSwitcher from '../../AttributeList/AttributeListSwitcher.tsx';
import {formatNumber} from '../../../lib/numbers.ts';
import RestoreFromTrashIcon from '@mui/icons-material/RestoreFromTrash';
import RestoreAssetsConfirm from '../../Media/Asset/Actions/RestoreAssetsConfirm.tsx';

const StyledToggleButtonGroup = styled(ToggleButtonGroup)(({theme}) => ({
    '& .MuiToggleButtonGroup-grouped': {
        'border': 0,
        'margin': theme.spacing(0.5),
        '&.Mui-disabled': {
            border: 0,
        },
        '&:not(:first-of-type)': {
            borderRadius: theme.shape.borderRadius,
        },
        '&:first-of-type': {
            borderRadius: theme.shape.borderRadius,
        },
    },
}));

export type ItemLabelRendererProps = {
    values: {
        total: string;
        selection: string;
    };
    count: number;
    selectedCount: number;
};

export type ItemLabelRenderer = (
    props: ItemLabelRendererProps
) => React.ReactNode;

export type SelectionActionConfigProps = {
    noActions?: boolean;
    itemLabel?: ItemLabelRenderer;
};

export type SelectionActionsProps<Item extends AssetOrAssetContainer> = {
    layout: Layout;
    setLayout?: StateSetter<Layout>;
    loading: boolean;
    total?: number;
    pages: Item[][];
    reload?: ReloadFunc;
    onOpenDebug?: VoidFunction;
    selectionContext: Context<TSelectionContext<Item>>;
    actionsContext: ActionsContext<Item>;
} & SelectionActionConfigProps;

export default function SelectionActions<Item extends AssetOrAssetContainer>({
    layout,
    setLayout,
    loading,
    total,
    pages,
    reload,
    onOpenDebug,
    actionsContext,
    noActions,
    selectionContext,
    itemLabel,
}: SelectionActionsProps<Item>) {
    const {t, i18n} = useTranslation();
    const navigateToModal = useNavigateToModal();
    const {openModal} = useModals();
    const {isAuthenticated} = useAuth();
    const {selection, disabledAssets, setSelection, itemToAsset} =
        useContext(selectionContext);
    const realSelectionLength = selection.filter(
        a => !disabledAssets.includes(a)
    ).length;
    const selectionLength = selection.length;
    const hasSelection = selectionLength > 0;
    const allSelected =
        hasSelection &&
        selectionLength ===
            pages.reduce((currentCount, row) => currentCount + row.length, 0);

    const toggleSelectAll = useCallback(() => {
        setSelection(previous => (previous.length > 0 ? [] : pages.flat()));
    }, [pages]);

    const {
        canDelete,
        canRestore,
        canDownload,
        canEdit,
        canEditAttributes,
        canMove,
        canShare,
        onShare,
        onDelete,
        onRestore,
        onCopy,
        onMove,
        onEdit,
        onEditAttributes,
        download,
    } = useMemo(() => {
        let canDelete = false;
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

    const selectAllDisabled = (total ?? 0) === 0;

    const locale = i18n.language;
    const selectionProps: ItemLabelRendererProps = {
        values: {
            total: formatNumber(total ?? 0, locale),
            selection: formatNumber(realSelectionLength, locale),
        },
        count: total ?? 0,
        selectedCount: realSelectionLength,
    };

    return (
        <Box
            sx={theme => ({
                [theme.breakpoints.up('md')]: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                },
            })}
        >
            <Box
                sx={theme => ({
                    '> .MuiButtonBase-root, > .MuiButtonGroup-root, > span': {
                        m: 1,
                    },
                    [theme.breakpoints.down('md')]: {
                        '.MuiButton-startIcon': {
                            display: 'none',
                        },
                    },
                })}
            >
                <Tooltip
                    title={
                        hasSelection
                            ? t('asset_actions.unselect_all', 'Unselect all')
                            : t('asset_actions.select_all', 'Select all')
                    }
                >
                    <span>
                        <Button
                            disabled={selectAllDisabled}
                            variant={'contained'}
                            sx={theme => ({
                                '.MuiCheckbox-root': {
                                    'p': 0,
                                    'm': 0,
                                    'color': theme.palette.primary.contrastText,
                                    '&.Mui-checked, &.MuiCheckbox-indeterminate':
                                        {
                                            m: 0,
                                            color: theme.palette.primary
                                                .contrastText,
                                        },
                                },
                            })}
                            onClick={toggleSelectAll}
                        >
                            <Badge
                                badgeContent={selectionLength}
                                color="secondary"
                            >
                                <Checkbox
                                    indeterminate={!allSelected && hasSelection}
                                    checked={allSelected}
                                    disabled={selectAllDisabled}
                                />
                            </Badge>
                        </Button>
                    </span>
                </Tooltip>

                {!noActions ? (
                    <>
                        {actionsContext.basket && isAuthenticated() ? (
                            <BasketSwitcher
                                selectionContext={selectionContext}
                            />
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
                                    !canEdit ||
                                    (selection.length > 0 && !canEditAttributes)
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
                        {actionsContext.delete && !canRestore ? (
                            <Button
                                disabled={!canDelete}
                                color={'error'}
                                onClick={onDelete}
                                variant={'contained'}
                                startIcon={<DeleteForeverIcon />}
                            >
                                {t('asset_actions.delete', 'Delete')}
                            </Button>
                        ) : actionsContext.restore ? (
                            <Button
                                disabled={!canRestore}
                                color={'error'}
                                onClick={onRestore}
                                variant={'contained'}
                                startIcon={<RestoreFromTrashIcon />}
                            >
                                {t('asset_actions.restore', 'Restore')}
                            </Button>
                        ) : (
                            ''
                        )}
                        {actionsContext.extraActions?.map(a => {
                            return (
                                <Button
                                    key={a.name}
                                    startIcon={a.icon}
                                    color={a.color}
                                    {...(a.buttonProps ?? {})}
                                    disabled={
                                        selection.length === 0 || a.disabled
                                    }
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
                ) : (
                    ''
                )}
            </Box>
            <Paper
                elevation={0}
                sx={{
                    display: 'flex',
                    border: theme => `1px solid ${theme.palette.divider}`,
                    borderTop: 0,
                    borderRight: 0,
                    alignItems: 'center',
                    alignSelf: 'flex-start',
                }}
            >
                <Box
                    sx={{
                        px: 2,
                    }}
                >
                    {!loading && total !== undefined ? (
                        <Box
                            component={'span'}
                            style={
                                onOpenDebug ? {cursor: 'pointer'} : undefined
                            }
                            onClick={onOpenDebug}
                            sx={{
                                strong: {
                                    whiteSpace: 'nowrap',
                                },
                            }}
                        >
                            {itemLabel ? (
                                itemLabel(selectionProps)
                            ) : selection.length > 0 ? (
                                <Trans
                                    i18nKey={
                                        'selection_actions.x_result_with_selection'
                                    }
                                    defaults={`<strong>{{selection}} / {{total}}</strong> result`}
                                    tOptions={{
                                        defaultValue_other: `<strong>{{selection}} / {{total}}</strong> results`,
                                    }}
                                    count={selectionProps.count}
                                    values={selectionProps.values}
                                />
                            ) : (
                                <Trans
                                    i18nKey={'selection_actions.x_result'}
                                    defaults={`<strong>{{count}}</strong> result`}
                                    tOptions={{
                                        defaultValue_other: `<strong>{{total}}</strong> results`,
                                    }}
                                    count={selectionProps.count}
                                    values={selectionProps.values}
                                />
                            )}
                        </Box>
                    ) : (
                        t('common.loading', 'Loading…')
                    )}
                </Box>
                <Divider
                    flexItem
                    orientation="vertical"
                    sx={{mx: 0.5, my: 1}}
                />
                {actionsContext.layout && setLayout ? (
                    <>
                        <StyledToggleButtonGroup
                            value={layout}
                            exclusive
                            onChange={(_e, newValue) => {
                                if (newValue) {
                                    setLayout!(newValue);
                                }
                            }}
                        >
                            <TooltipToggleButton
                                tooltipProps={{
                                    title: t('layout.view.grid', 'Grid View'),
                                }}
                                value={Layout.Grid}
                            >
                                <GridViewIcon />
                            </TooltipToggleButton>
                            <TooltipToggleButton
                                tooltipProps={{
                                    title: t('layout.view.list', 'List View'),
                                }}
                                value={Layout.List}
                            >
                                <ViewListIcon />
                            </TooltipToggleButton>
                            <TooltipToggleButton
                                tooltipProps={{
                                    title: t(
                                        'layout.view.masonry',
                                        'Masonry View'
                                    ),
                                }}
                                value={Layout.Masonry}
                            >
                                <ViewQuiltIcon />
                            </TooltipToggleButton>
                        </StyledToggleButtonGroup>
                        <Divider
                            flexItem
                            orientation="vertical"
                            sx={{mx: 0.5, my: 1}}
                        />
                    </>
                ) : (
                    ''
                )}

                <AttributeListSwitcher />
                <DisplayOptionsMenu />
            </Paper>
        </Box>
    );
}
