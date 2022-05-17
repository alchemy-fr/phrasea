import React, {CSSProperties, MouseEvent, useCallback, useContext, useState} from "react";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {Box, Button, LinearProgress, ListSubheader} from "@mui/material";
import {ResultContext} from "./ResultContext";
import Pager, {LAYOUT_GRID} from "./Pager";
import SearchBar from "./SearchBar";
import SelectionActions from "./SelectionActions";
import {Asset} from "../../../types";
import {useTranslation} from "react-i18next";
import ArrowCircleDownIcon from '@mui/icons-material/ArrowCircleDown';
import {LoadingButton} from "@mui/lab";

const gridStyle: CSSProperties = {
    width: '100%',
    height: '100%',
    overflow: 'auto',
};

const linearProgressStyle: CSSProperties = {
    position: 'absolute',
    left: '0',
    right: '0',
    top: '0',
};

function getAssetListFromEvent(currentSelection: string[], id: string, e: MouseEvent, pages: Asset[][]): string[] {
    if (e.ctrlKey) {
        return currentSelection.includes(id) ? currentSelection.filter(a => a !== id) : currentSelection.concat([id]);
    }
    if (e.shiftKey && currentSelection.length > 0) {
        let boundaries: [[number, number] | undefined, [number, number] | undefined] = [undefined, undefined];

        for (let i = 0; i < pages.length; ++i) {
            const assets = pages[i];
            for (let j = 0; j < assets.length; ++j) {
                const a = assets[j];
                if (currentSelection.includes(a.id) || id === a.id) {
                    boundaries = [boundaries[0] ?? [i, j], [i, j]];
                }
            }
        }

        const selection = [];
        for (let i = boundaries[0]![0]; i <= boundaries[1]![0]; ++i) {
            const start = i === boundaries[0]![0] ? boundaries[0]![1] : 0;
            const end = i === boundaries[1]![0] ? boundaries[1]![1] : pages[i].length - 1;
            for (let j = start; j <= end; ++j) {
                selection.push(pages[i][j].id);
            }
        }

        return selection;
    }

    return [id];
}

export default function AssetResults() {
    const assetSelection = useContext(AssetSelectionContext);
    const resultContext = useContext(ResultContext);
    const {t} = useTranslation();
    const [layout, setLayout] = useState(LAYOUT_GRID);

    const onSelect = useCallback((id: string, e: MouseEvent): void => {
        e.preventDefault();
        const ids = getAssetListFromEvent(assetSelection.selectedAssets, id, e, resultContext.pages);
        assetSelection.selectAssets(ids);
        // eslint-disable-next-line
    }, [assetSelection.selectAssets, assetSelection.selectedAssets]);

    const {loading, pages, loadMore} = resultContext;

    return <div style={{
        position: 'relative',
        height: '100%',
    }}>
        <div style={gridStyle}>
            {loading && <div style={linearProgressStyle}>
                <LinearProgress/>
            </div>}
            <>
                <SearchBar/>
                <ListSubheader
                    component="div"
                    disableGutters={true}
                >
                    <SelectionActions
                        layout={layout}
                        onLayoutChange={(l) => setLayout(l)}
                    />
                </ListSubheader>
                <Pager
                    pages={pages}
                    layout={layout}
                    selectedAssets={assetSelection.selectedAssets}
                    onSelect={onSelect}
                />
            </>
            {loadMore ? <Box
                sx={{
                    textAlign: 'center',
                    mb: 4,
                }}
            >
                <LoadingButton
                    loading={loading}
                    startIcon={<ArrowCircleDownIcon/>}
                    onClick={loadMore}
                    variant="contained"
                    color="secondary"
                >
                    {loading ? t('load_more.button.loading', 'Loading...') : t('load_more.button.loading', 'Load more')}
                </LoadingButton>
            </Box> : ''}
        </div>
    </div>
}
