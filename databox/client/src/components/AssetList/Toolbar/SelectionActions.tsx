// @ts-nocheck
import {Context, useCallback, useContext, useMemo} from 'react';
import {Badge, Box, Button, Checkbox, Divider, Paper, ToggleButtonGroup, Tooltip,} from '@mui/material';
import {useTranslation} from 'react-i18next';
import GridViewIcon from '@mui/icons-material/GridView';
import ViewListIcon from '@mui/icons-material/ViewList';
import DeleteIcon from '@mui/icons-material/Delete';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import ShareIcon from '@mui/icons-material/Share';
import TooltipToggleButton from '../../Ui/TooltipToggleButton.tsx';
import {AssetSelectionContext, TSelectionContext,} from '../../../context/AssetSelectionContext.tsx';
import {styled} from '@mui/material/styles';
import DeleteAssetsConfirm from '../../Media/Asset/Actions/DeleteAssetsConfirm.tsx';
import DisplayOptionsMenu from './DisplayOptionsMenu.tsx';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../../types.ts';
import {LoadingButton} from '@mui/lab';
import ExportAssetsDialog from '../../Media/Asset/Actions/ExportAssetsDialog.tsx';
import GroupButton from '../../Ui/GroupButton.tsx';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import EditIcon from '@mui/icons-material/Edit';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import MoveAssetsDialog from '../../Media/Asset/Actions/MoveAssetsDialog.tsx';
import CopyAssetsDialog from '../../Media/Asset/Actions/CopyAssetsDialog.tsx';
import TextSnippetIcon from '@mui/icons-material/TextSnippet';
import {useModals} from '@alchemy/navigation';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import {modalRoutes} from '../../../routes.ts';
import BasketSwitcher from "../../Basket/BasketSwitcher.tsx";
import {Layout} from "../Layouts";
import {ItemToAssetFunc} from "../types.ts";

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

export type SelectionActionsProps<Item extends AssetOrAssetContainer> = {
    layout: Layout;
    setLayout: StateSetter<Layout>;
    loading: boolean;
    total?: number;
    pages: Item[][];
    reload: () => void;
    onOpenDebug?: VoidFunction;
    selectionContext: Context<TSelectionContext<Item>>;
};

export default function SelectionActions<Item extends AssetOrAssetContainer>({
    layout,
    setLayout,
    loading,
    total,
    pages,
    reload,
    onOpenDebug,
    selectionContext,
}: SelectionActionsProps<Item>) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
    const {openModal} = useModals();
    const {selection, setSelection, itemToAsset} = useContext(selectionContext);

    const selectionLength = selection.length;
    const hasSelection = selectionLength > 0;
    const allSelected =
        hasSelection &&
        selectionLength ===
        pages.reduce(
            (currentCount, row) => currentCount + row.length,
            0
        );

    const toggleSelectAll = useCallback(() => {
        setSelection(previous => previous.length > 0 ? [] : pages.flat());
    }, [pages]);

    const onDelete = () => {
        openModal(DeleteAssetsConfirm, {
            assetIds: selection.map(i => i.id),
            onDelete: () => {
                reload();
            },
        });
    };

    const {
        canDelete,
        canDownload,
        canEdit,
        canEditAttributes,
        canMove,
        canShare,
        onCopy,
        wsId,
    } = useMemo(() => {
        let canDelete = false;
        let canDownload = false;
        let canEdit = false;
        let canEditAttributes = false;
        let canMove = false;
        let canShare = false;
        let wsId: string | undefined = undefined;

        const selectedAssets = itemToAsset ? selection.map(itemToAsset) : (selection as unknown as Asset);

        selectedAssets.forEach((a: Asset) => {
            wsId = a.workspace.id;
            if (a.original?.file?.url) {
                canDownload = true;
            }
            if (a.capabilities.canDelete) {
                canDelete = true;
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
            if (a.workspace.id !== wsId!) {
                canEditAttributes = canMove = false;
                break;
            }
        }

        return {
            canDelete,
            canDownload,
            canEdit,
            canEditAttributes,
            canMove,
            canShare,
            onCopy: () => {
                openModal(CopyAssetsDialog, {
                    assets: selectedAssets,
                    onComplete: () => {
                        reload();
                    },
                });
            },
            wsId,
        };
    }, [selection]);

    const onMove = () => {
        openModal(MoveAssetsDialog, {
            assetIds: selection.map(i => i.id),
            workspaceId: wsId!,
            onComplete: () => {
                reload();
            },
        });
    };

    const onEdit = () => {
        if (selection.length === 1) {
            navigateToModal(modalRoutes.assets.routes.manage, {
                tab: 'edit',
                id: selection[0].id,
            });
        } else {
            alert('Multi edit is comin soon...');
        }
    };

    const onEditAttributes = () => {
        if (selection.length === 1) {
            navigateToModal(modalRoutes.assets.routes.manage, {
                tab: 'attributes',
                id: selection[0].id,
            });
        } else {
            alert('Multi edit attributes is comin soon...');
        }
    };

    const download = canDownload
        ? () => {
            openModal(ExportAssetsDialog, {
                assets: selection.map(itemToAsset),
            });
        }
        : undefined;

    const selectAllDisabled = (total ?? 0) === 0;

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

                <BasketSwitcher/>
                <LoadingButton
                    disabled={!canDownload}
                    variant={'contained'}
                    onClick={download}
                    startIcon={<FileDownloadIcon/>}
                >
                    {t('asset_actions.export', 'Export')}
                </LoadingButton>
                <GroupButton
                    id={'edit'}
                    onClick={onEdit}
                    startIcon={<EditIcon/>}
                    disabled={!canEdit}
                    actions={[
                        {
                            id: 'move',
                            label: t('asset_actions.move', 'Move'),
                            onClick: onMove,
                            disabled: !canMove,
                            startIcon: <DriveFileMoveIcon/>,
                        },
                        {
                            id: 'edit_attrs',
                            label: t(
                                'asset_actions.edit_attributes',
                                'Edit attributes'
                            ),
                            onClick: onEditAttributes,
                            disabled: !canEditAttributes,
                            startIcon: <TextSnippetIcon/>,
                        },
                        {
                            id: 'copy',
                            label: t('asset_actions.copy', 'Copy'),
                            onClick: onCopy,
                            disabled: !canShare,
                            startIcon: <FileCopyIcon/>,
                        },
                    ]}
                >
                    {t('asset_actions.edit', 'Edit')}
                </GroupButton>
                <Button
                    disabled={!canShare}
                    variant={'contained'}
                    startIcon={<ShareIcon/>}
                >
                    {t('asset_actions.share', 'Share')}
                </Button>
                <Button
                    disabled={!canDelete}
                    color={'error'}
                    onClick={onDelete}
                    variant={'contained'}
                    startIcon={<DeleteIcon/>}
                >
                    {t('asset_actions.delete', 'Delete')}
                </Button>
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
                    {!loading &&
                    total !== undefined ? (
                        <>
                            <b>
                                {new Intl.NumberFormat('fr-FR', {}).format(
                                    total
                                )}
                            </b>
                            <span
                                style={{cursor: 'pointer'}}
                                onClick={onOpenDebug}
                            >
                                {` result${total > 1 ? 's' : ''}`}
                            </span>
                        </>
                    ) : (
                        t('common.loading', 'Loading...')
                    )}
                </Box>
                <Divider
                    flexItem
                    orientation="vertical"
                    sx={{mx: 0.5, my: 1}}
                />
                <StyledToggleButtonGroup
                    value={layout}
                    exclusive
                    onChange={(_e, newValue) => setLayout(newValue)}
                >
                    <TooltipToggleButton
                        tooltipProps={{
                            title: t('layout.view.grid', 'Grid view'),
                        }}
                        value={Layout.Grid}
                    >
                        <GridViewIcon/>
                    </TooltipToggleButton>
                    <TooltipToggleButton
                        tooltipProps={{
                            title: t('layout.view.list', 'List view'),
                        }}
                        value={Layout.List}
                    >
                        <ViewListIcon/>
                    </TooltipToggleButton>
                </StyledToggleButtonGroup>
                <Divider
                    flexItem
                    orientation="vertical"
                    sx={{mx: 0.5, my: 1}}
                />
                <DisplayOptionsMenu/>
            </Paper>
        </Box>
    );
}
