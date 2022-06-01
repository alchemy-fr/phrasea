import React, {CSSProperties, useCallback, useContext, useEffect, useMemo, useState} from 'react';
import {Asset} from "../../../types";
import AssetSelectionProvider from "../AssetSelectionProvider";
import {ResultContext} from "../Search/ResultContext";
import Pager, {LayoutEnum} from "../Search/Pager";
import {OnSelectAsset, OnUnselectAsset} from "../Search/Layout/Layout";
import {getAssetListFromEvent} from "../Search/AssetResults";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {DisplayContext, TDisplayContext} from "../DisplayContext";
import {voidFunc} from "../../../lib/utils";
import {Box} from "@mui/material";

type Props = {
    assets: Asset[];
    onSelectionChange: (selection: string[]) => void;
} & {
    style?: CSSProperties;
};

function SelectionProxy({
                            assets,
                        }: {
    assets: Asset[];
}) {
    const assetSelection = useContext(AssetSelectionContext);
    const [pages, setPages] = useState([assets]);

    useEffect(() => {
        setPages([assets]);
    }, [assets]);

    const onSelect = useCallback<OnSelectAsset>((id, e): void => {
        e?.preventDefault();
        assetSelection.selectAssets((prev) => {
            return getAssetListFromEvent(prev, id, pages, e)
        });
        // eslint-disable-next-line
    }, [assetSelection]);

    const onUnselect = useCallback<OnUnselectAsset>((id, e): void => {
        e?.preventDefault();
        assetSelection.selectAssets(p => p.filter(i => i !== id));
        // eslint-disable-next-line
    }, [assetSelection]);

    return <Pager
        pages={pages}
        layout={LayoutEnum.List}
        selectedAssets={assetSelection.selectedAssets}
        onSelect={onSelect}
        onUnselect={onUnselect}
    />
}

export default function AssetSelection({
                                           assets,
                                           onSelectionChange,
                                           style,
                                       }: Props) {
    const displayContext: TDisplayContext = useMemo(() => ({
        collectionsLimit: 1,
        displayAttributes: false,
        displayCollections: true,
        displayPreview: false,
        displayTags: true,
        displayTitle: true,
        playVideos: false,
        playing: undefined,
        previewLocked: false,
        setCollectionsLimit: voidFunc,
        setPlaying: voidFunc,
        setTagsLimit: voidFunc,
        setThumbSize: voidFunc,
        setTitleRows: voidFunc,
        tagsLimit: 1,
        thumbSize: 150,
        titleRows: 1,
        toggleDisplayAttributes: voidFunc,
        toggleDisplayCollections: voidFunc,
        toggleDisplayPreview: voidFunc,
        toggleDisplayTags: voidFunc,
        toggleDisplayTitle: voidFunc,
        togglePlayVideos: voidFunc,
    }), []);

    return <Box
        sx={theme => ({
        color: theme.palette.common.black,
            width: '100%',
    })}
        style={style}
    >
        <ResultContext.Provider
            value={{
                loading: false,
                pages: [assets],
                total: assets.length,
                reload: () => {
                },
            }}
        >
            <AssetSelectionProvider
                onSelectionChange={onSelectionChange}
            >
                <DisplayContext.Provider value={displayContext}>
                    <SelectionProxy
                        assets={assets}
                    />
                </DisplayContext.Provider>
            </AssetSelectionProvider>
        </ResultContext.Provider>
    </Box>
}
