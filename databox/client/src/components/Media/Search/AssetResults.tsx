import React, {CSSProperties, MouseEvent, useCallback, useContext, useState} from "react";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {Button, LinearProgress, ListSubheader} from "@mui/material";
import {ResultContext} from "./ResultContext";
import Pager, {LAYOUT_GRID, LAYOUT_LIST} from "./Pager";
import SearchFilters from "./SearchFilters";
import DebugEsModal from "./DebugEsModal";
import SearchBar from "./SearchBar";

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

function getAssetListFromEvent(currentSelection: string[], id: string, e: MouseEvent): string[] {
    if (e.ctrlKey) {
        return currentSelection.includes(id) ? currentSelection.filter(a => a !== id) : currentSelection.concat([id]);
    }

    return [id];
}

export default function AssetResults() {
    const assetSelection = useContext(AssetSelectionContext);
    const search = useContext(ResultContext);

    const [layout, setLayout] = useState(LAYOUT_GRID);
    const [debugOpen, setDebugOpen] = useState(false);

    const onSelect = useCallback((id: string, e: MouseEvent): void => {
        const ids = getAssetListFromEvent(assetSelection.selectedAssets, id, e);
        assetSelection.selectAssets(ids);
        // eslint-disable-next-line
    }, [assetSelection.selectAssets]);

    const {loading, total, loadMore, pages, debug} = search;

    return <div style={{
        position: 'relative',
        height: '100%',
    }}>
        <div style={gridStyle}>
            {loading && <div style={linearProgressStyle}>
                <LinearProgress/>
            </div>}
            <div>
                <SearchBar />
                <SearchActions />
                <ListSubheader component="div" className={'result-info'}>

                    {' '}
                    {!loading && total !== undefined ? <>
                        <b>
                            {new Intl.NumberFormat('fr-FR', {}).format(total)}
                        </b>
                        {debugOpen && debug && <DebugEsModal
                            onClose={() => setDebugOpen(false)}
                            debug={debug}
                        />}
                        <span
                            style={{cursor: 'pointer'}}
                        onClick={() => setDebugOpen(true)}>
                            {` result${total > 1 ? 's' : ''}`}
                        </span>
                    </> : 'Loading...'}
                </ListSubheader>
                <div className={'asset-result'}>
                    <Pager
                        pages={pages}
                        layout={layout}
                        selectedAssets={assetSelection.selectedAssets}
                        onSelect={onSelect}
                    />
                </div>
            </div>
            {loadMore ? <div className={'text-center mb-3'}>
                <Button
                    disabled={loading}
                    onClick={loadMore}
                    variant="contained"
                    color="secondary"
                >
                    {loading ? 'Loading...' : 'Load more'}
                </Button>
            </div> : ''}
        </div>
    </div>
}
