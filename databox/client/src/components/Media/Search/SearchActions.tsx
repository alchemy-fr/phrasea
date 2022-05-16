import React, {useCallback, useContext} from 'react';
import {Box, Button, ButtonGroup, Checkbox, ToggleButton, ToggleButtonGroup, Tooltip} from "@mui/material";
import {useTranslation} from "react-i18next";
import {LAYOUT_GRID, LAYOUT_LIST} from "./Pager";
import GridViewIcon from '@mui/icons-material/GridView';
import ViewListIcon from '@mui/icons-material/ViewList';
import DeleteIcon from '@mui/icons-material/Delete';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import EditIcon from '@mui/icons-material/Edit';
import ShareIcon from '@mui/icons-material/Share';
import TooltipToggleButton from "../../Ui/TooltipToggleButton";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {ResultContext} from "./ResultContext";

type Props = {
    layout: number;
    onLayoutChange: (l: number) => void;
};

export default function SearchActions({
                                          layout,
                                          onLayoutChange
                                      }: Props) {
    const {t} = useTranslation();
    const selectionContext = useContext(AssetSelectionContext);
    const resultContext = useContext(ResultContext);

    const hasSelection = selectionContext.selectedAssets.length > 0;
    const allSelected = selectionContext.selectedAssets.length === resultContext.pages.reduce((currentCount, row) => currentCount + row.length, 0);

    const toggleSelectAll = useCallback(() => {
        console.log('allSelected', allSelected);
        if (hasSelection) {
            selectionContext.selectAssets([]);
        } else {
            console.log('resultContext.pages.map(p => p.map(a => a.id)).flat()', resultContext.pages.map(p => p.map(a => a.id)).flat());
            selectionContext.selectAssets(resultContext.pages.map(p => p.map(a => a.id)).flat());
        }
    }, [selectionContext.selectedAssets, hasSelection]);

    return <Box
        sx={(theme) => ({
            display: 'flex',
            justifyContent: 'space-between',
        })}
    >
        <Box
            sx={(theme) => ({
                '.MuiButtonBase-root': {
                    m: 1,
                }
            })}
        >
            <Tooltip
                title={hasSelection ? t('asset_actions.unselect_all', 'Unselect all') : t('asset_actions.select_all', 'Select all')}
            >
                <Button
                    variant={'contained'}
                    sx={(theme) => ({
                        '.MuiCheckbox-root': {
                            p: 0,
                            m: 0,
                            color: theme.palette.primary.contrastText,
                            '&:checked': {
                                m: 0,
                                color: theme.palette.primary.contrastText,
                            }
                        }
                    })}
                    onClick={toggleSelectAll}
                >
                    <Checkbox
                        indeterminate={!allSelected && hasSelection}
                        checked={allSelected}
                    />
                </Button>
            </Tooltip>
            <Button
                variant={'contained'}
                startIcon={<FileDownloadIcon/>}
            >
                {t('asset_actions.export', 'Export')}
            </Button>
            <Button
                variant={'contained'}
                startIcon={<EditIcon/>}
            >
                {t('asset_actions.edit', 'Edit')}
            </Button>
            <Button
                variant={'contained'}
                startIcon={<ShareIcon/>}
            >
                {t('asset_actions.share', 'Share')}
            </Button>
            <Button
                color={'error'}
                variant={'contained'}
                startIcon={<DeleteIcon/>}
            >
                {t('asset_actions.delete', 'Delete')}
            </Button>
        </Box>

        <ToggleButtonGroup
            value={layout}
            exclusive
            onChange={(e, newValue) => onLayoutChange(newValue)}
        >
                <TooltipToggleButton
                    tooltipProps={{title: t('layout.view.grid', 'Grid view')}}
                    value={LAYOUT_GRID}
                >
                    <GridViewIcon />
                </TooltipToggleButton>
                <TooltipToggleButton 
                    tooltipProps={{title: t('layout.view.list', 'List view')}}
                    value={LAYOUT_LIST}>
                    <ViewListIcon />
                </TooltipToggleButton>
        </ToggleButtonGroup>
    </Box>
}
