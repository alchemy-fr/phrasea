// @ts-nocheck
import {useCallback, useContext, useMemo} from 'react';
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
import {useTranslation} from 'react-i18next';
import {LayoutEnum} from './Pager';
import GridViewIcon from '@mui/icons-material/GridView';
import ViewListIcon from '@mui/icons-material/ViewList';
import DeleteIcon from '@mui/icons-material/Delete';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import ShareIcon from '@mui/icons-material/Share';
import TooltipToggleButton from '../../Ui/TooltipToggleButton';
import {
    AssetSelectionContext,
    TSelectionContext,
} from '../../../context/AssetSelectionContext.tsx';
import {ResultContext, TResultContext} from './ResultContext';
import DebugEsModal from './DebugEsModal';
import {styled} from '@mui/material/styles';
import DeleteAssetsConfirm from '../Asset/Actions/DeleteAssetsConfirm';
import DisplayOptionsMenu from './DisplayOptionsMenu';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../../types';
import {LoadingButton} from '@mui/lab';
import ExportAssetsDialog from '../Asset/Actions/ExportAssetsDialog';
import GroupButton from '../../Ui/GroupButton';
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import EditIcon from '@mui/icons-material/Edit';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import MoveAssetsDialog from '../Asset/Actions/MoveAssetsDialog';
import CopyAssetsDialog from '../Asset/Actions/CopyAssetsDialog';
import TextSnippetIcon from '@mui/icons-material/TextSnippet';
import {useModals} from '@alchemy/navigation';
import {useNavigateToModal} from '../../Routing/ModalLink';
import {modalRoutes} from '../../../routes.ts';
import BasketSwitcher from "../../Basket/BasketSwitcher.tsx";
import {Layout} from "../../AssetList/Layouts";

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

type Props = {
    layout: Layout;
    setLayout: StateSetter<Layout>;
};

export default function SelectionActions({layout, setLayout}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
    const {openModal} = useModals();
    const selectionContext = useContext(AssetSelectionContext);
    const resultContext = useContext(ResultContext);

    const selectionLength = selectionContext.selection.length;
    const hasSelection = selectionLength > 0;
    const allSelected =
        hasSelection &&
        selectionLength ===
            resultContext.pages.reduce(
                (currentCount, row) => currentCount + row.length,
                0
            );

    const toggleSelectAll = useCallback(() => {
        selectionContext.setSelection(
            hasSelection
                ? []
                : resultContext.pages.map(p => p.map(a => a.id)).flat()
        );
    }, [resultContext.pages, selectionContext.selection, hasSelection]);

    const openDebug = resultContext.debug
        ? () => {
              openModal(DebugEsModal, {
                  debug: resultContext.debug!,
              });
          }
        : undefined;

    const onDelete = () => {
        openModal(DeleteAssetsConfirm, {
            assetIds: selectionContext.selection.map(i => i.id),
            onDelete: () => {
                resultContext.reload();
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

        const selectedAssets = selectionContext.selection;

        selectedAssets.forEach(a => {
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
                        resultContext.reload();
                    },
                });
            },
            wsId,
        };
    }, [selectionContext.selection]);

    const onMove = () => {
        openModal(MoveAssetsDialog, {
            assetIds: selectionContext.selection.map(i => i.id),
            workspaceId: wsId!,
            onComplete: () => {
                resultContext.reload();
            },
        });
    };

    const onEdit = () => {
        if (selectionContext.selection.length === 1) {
            navigateToModal(modalRoutes.assets.routes.manage, {
                tab: 'edit',
                id: selectionContext.selection[0],
            });
        } else {
            alert('Multi edit is comin soon...');
        }
    };

    const onEditAttributes = () => {
        const assets = getSelectedAssets(selectionContext, resultContext);
        if (assets.length === 1) {
            navigateToModal(modalRoutes.assets.routes.manage, {
                tab: 'attributes',
                id: selectionContext.selection[0],
            });
        } else {
            alert('Multi edit attributes is comin soon...');
        }
    };

    const download = canDownload
        ? () => {
              openModal(ExportAssetsDialog, {
                  assets: getSelectedAssets(selectionContext, resultContext),
              });
          }
        : undefined;

    const selectAllDisabled = (resultContext.total ?? 0) === 0;

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
                    startIcon={<FileDownloadIcon />}
                >
                    {t('asset_actions.export', 'Export')}
                </LoadingButton>
                <GroupButton
                    id={'edit'}
                    onClick={onEdit}
                    startIcon={<EditIcon />}
                    disabled={!canEdit}
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
                <Button
                    disabled={!canShare}
                    variant={'contained'}
                    startIcon={<ShareIcon />}
                >
                    {t('asset_actions.share', 'Share')}
                </Button>
                <Button
                    disabled={!canDelete}
                    color={'error'}
                    onClick={onDelete}
                    variant={'contained'}
                    startIcon={<DeleteIcon />}
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
                    {!resultContext.loading &&
                    resultContext.total !== undefined ? (
                        <>
                            <b>
                                {new Intl.NumberFormat('fr-FR', {}).format(
                                    resultContext.total
                                )}
                            </b>
                            <span
                                style={{cursor: 'pointer'}}
                                onClick={openDebug}
                            >
                                {` result${resultContext.total > 1 ? 's' : ''}`}
                            </span>
                        </>
                    ) : (
                        'Loading...'
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
                        <GridViewIcon />
                    </TooltipToggleButton>
                    <TooltipToggleButton
                        tooltipProps={{
                            title: t('layout.view.list', 'List view'),
                        }}
                        value={Layout.List}
                    >
                        <ViewListIcon />
                    </TooltipToggleButton>
                </StyledToggleButtonGroup>
                <Divider
                    flexItem
                    orientation="vertical"
                    sx={{mx: 0.5, my: 1}}
                />
                <DisplayOptionsMenu />
            </Paper>
        </Box>
    );
}
