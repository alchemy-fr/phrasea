import React, {useCallback, useContext, useMemo} from 'react';
import {Badge, Box, Button, Checkbox, Divider, Paper, ToggleButtonGroup, Tooltip} from "@mui/material";
import {useTranslation} from "react-i18next";
import {LAYOUT_GRID, LAYOUT_LIST} from "./Pager";
import GridViewIcon from '@mui/icons-material/GridView';
import ViewListIcon from '@mui/icons-material/ViewList';
import DeleteIcon from '@mui/icons-material/Delete';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import ShareIcon from '@mui/icons-material/Share';
import TooltipToggleButton from "../../Ui/TooltipToggleButton";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {ResultContext} from "./ResultContext";
import {useModals} from "@mattjennings/react-modal-stack";
import DebugEsModal from "./DebugEsModal";
import {styled} from "@mui/material/styles";
import DeleteAssetsConfirm from "../Asset/Actions/DeleteAssetsConfirm";
import DisplayOptionsMenu from "./DisplayOptionsMenu";
import {Asset} from "../../../types";
import {LoadingButton} from "@mui/lab";
import ExportAssetsDialog from "../Asset/Actions/ExportAssetsDialog";
import GroupButton from "../../Ui/GroupButton";
import DriveFileMoveIcon from '@mui/icons-material/DriveFileMove';
import EditIcon from '@mui/icons-material/Edit';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import MoveAssetsDialog from "../Asset/Actions/MoveAssetsDialog";

const StyledToggleButtonGroup = styled(ToggleButtonGroup)(({theme}) => ({
    '& .MuiToggleButtonGroup-grouped': {
        border: 0,
        margin: theme.spacing(0.5),
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
    layout: number;
    onLayoutChange: (l: number) => void;
};

function getAsset(pages: Asset[][], id: string): Asset {
    for (let i = 0; i < pages.length; i++) {
        const p = pages[i];
        const a = p.find(a => a.id === id);
        if (a) {
            return a;
        }
    }

    throw new Error(`Undefined asset ${id}`);
}

export default function SelectionActions({
                                             layout,
                                             onLayoutChange,
                                         }: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const selectionContext = useContext(AssetSelectionContext);
    const resultContext = useContext(ResultContext);

    const selectionLength = selectionContext.selectedAssets.length;
    const hasSelection = selectionLength > 0;
    const allSelected = hasSelection && selectionLength === resultContext.pages.reduce((currentCount, row) => currentCount + row.length, 0);

    const toggleSelectAll = useCallback(() => {
        selectionContext.selectAssets(
            hasSelection ?
                []
                : resultContext.pages.map(p => p.map(a => a.id)).flat()
        );
    }, [resultContext.pages, selectionContext.selectedAssets, hasSelection]);

    const openDebug = resultContext.debug ? () => {
        openModal(DebugEsModal, {
            debug: resultContext.debug!,
        });
    } : undefined;

    const onDelete = () => {
        openModal(DeleteAssetsConfirm, {
            assetIds: selectionContext.selectedAssets,
            onDelete: () => {
                resultContext.reload();
            }
        });
    };

    const onMove = () => {
        openModal(MoveAssetsDialog, {
            assetIds: selectionContext.selectedAssets,
            onComplete: () => {
                resultContext.reload();
            }
        });
    };

    const {
        canDownload,
        canDelete,
        canEdit,
    } = useMemo(() => {
        let canDownload = false;
        let canDelete = false;
        let canEdit = false;
        selectionContext.selectedAssets.map(id => getAsset(resultContext.pages, id)).forEach(a => {
            if (a?.original?.url) {
                canDownload = true;
            }
            if (a?.capabilities.canDelete) {
                canDelete = true;
            }
            if (a?.capabilities.canEdit) {
                canEdit = true;
            }
        });

        return {
            canDownload,
            canDelete,
            canEdit,
        };
    }, [selectionContext.selectedAssets]);

    const download = canDownload ? () => {
        openModal(ExportAssetsDialog, {
            assets: selectionContext.selectedAssets.map(id => getAsset(resultContext.pages, id)),
        })
    } : undefined;

    const selectAllDisabled = (resultContext.total ?? 0) === 0;

    return <Box
        sx={(theme) => ({
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
        })}
    >
        <Box
            sx={(theme) => ({
                '> .MuiButtonBase-root, > .MuiButtonGroup-root': {
                    m: 1,
                }
            })}
        >
            <Tooltip
                title={hasSelection ? t('asset_actions.unselect_all', 'Unselect all') : t('asset_actions.select_all', 'Select all')}
            >
                <Button
                    disabled={selectAllDisabled}
                    variant={'contained'}
                    sx={(theme) => ({
                        '.MuiCheckbox-root': {
                            p: 0,
                            m: 0,
                            color: theme.palette.primary.contrastText,
                            '&.Mui-checked, &.MuiCheckbox-indeterminate': {
                                m: 0,
                                color: theme.palette.primary.contrastText,
                            }
                        }
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
            </Tooltip>
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
                onClick={() => {

                }}
                startIcon={<EditIcon/>}
                disabled={!canEdit}
                actions={[
                    {
                        id: 'move',
                        label: t('asset_actions.move', 'Move'),
                        onClick: onMove,
                        disabled: !canEdit,
                        startIcon: <DriveFileMoveIcon/>,
                    },
                    {
                        id: 'copy',
                        label: t('asset_actions.copy', 'Copy'),
                        onClick: () => {

                        },
                        disabled: !canEdit,
                        startIcon: <FileCopyIcon/>,
                    }
                ]}
            >
                {t('asset_actions.edit', 'Edit')}
            </GroupButton>
            <Button
                disabled={!hasSelection}
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
                border: (theme) => `1px solid ${theme.palette.divider}`,
                borderTop: 0,
                borderRight: 0,
                flexWrap: 'wrap',
                alignItems: 'center',
            }}
        >
            <Box sx={{
                px: 2,
            }}>
                {!resultContext.loading && resultContext.total !== undefined ? <>
                    <b>
                        {new Intl.NumberFormat('fr-FR', {}).format(resultContext.total)}
                    </b>
                    <span
                        style={{cursor: 'pointer'}}
                        onClick={openDebug}>
                                {` result${resultContext.total > 1 ? 's' : ''}`}
                            </span>
                </> : 'Loading...'}
            </Box>
            <Divider flexItem orientation="vertical" sx={{mx: 0.5, my: 1}}/>
            <StyledToggleButtonGroup
                value={layout}
                exclusive
                onChange={(e, newValue) => onLayoutChange(newValue)}
            >
                <TooltipToggleButton
                    tooltipProps={{title: t('layout.view.grid', 'Grid view')}}
                    value={LAYOUT_GRID}
                >
                    <GridViewIcon/>
                </TooltipToggleButton>
                <TooltipToggleButton
                    tooltipProps={{title: t('layout.view.list', 'List view')}}
                    value={LAYOUT_LIST}>
                    <ViewListIcon/>
                </TooltipToggleButton>
            </StyledToggleButtonGroup>
            <Divider flexItem orientation="vertical" sx={{mx: 0.5, my: 1}}/>
            <DisplayOptionsMenu/>
        </Paper>
    </Box>
}
