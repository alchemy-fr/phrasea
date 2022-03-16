import React, {CSSProperties, MouseEvent, useCallback, useContext, useState} from "react";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {Button, LinearProgress, ListSubheader} from "@material-ui/core";
import {SearchContext} from "./SearchContext";
import Pager, {LAYOUT_GRID, LAYOUT_LIST} from "./Pager";
import SearchFilters from "./SearchFilters";

const classes = {
    root: {},
    gridList: {
        width: '100%',
    },
};

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
    const search = useContext(SearchContext);

    const [layout, setLayout] = useState(LAYOUT_GRID);

    const onSelect = useCallback((id: string, e: MouseEvent): void => {
        const ids = getAssetListFromEvent(assetSelection.selectedAssets, id, e);
        assetSelection.selectAssets(ids);
        // eslint-disable-next-line
    }, [assetSelection.selectAssets]);


    const {loading, total, loadMore, pages} = search;

    return <div style={{
        position: 'relative',
        height: '100%',
    }}>
        <div style={gridStyle}>
            {loading && <div style={linearProgressStyle}>
                <LinearProgress/>
            </div>}
            <div style={classes.root}>
                <ListSubheader component="div" className={'result-info'}>
                    <Button
                        color={layout === LAYOUT_GRID ? "primary" : undefined}
                        onClick={() => setLayout(LAYOUT_GRID)}>Grid</Button>

                    <Button
                        color={layout === LAYOUT_LIST ? "primary" : undefined}
                        onClick={() => setLayout(LAYOUT_LIST)}
                    >List</Button>
                    {' '}
                    {!loading && total !== undefined ? <>
                        <b>
                            {new Intl.NumberFormat('fr-FR', {}).format(total)}
                        </b>
                        {` result${total > 1 ? 's' : ''}`}
                    </> : 'Loading...'}

                    {search.attrFilters && <SearchFilters
                        onDelete={search.removeAttrFilter}
                        onInvert={search.invertAttrFilter}
                        filters={search.attrFilters}
                    />}
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
