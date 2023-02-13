import React, {CSSProperties, useCallback, useContext, useMemo} from 'react';
import {Asset} from "../../../types";
import AssetSelectionProvider from "../AssetSelectionProvider";
import {ResultContext} from "../Search/ResultContext";
import Pager, {LayoutEnum} from "../Search/Pager";
import {OnSelectAsset, OnUnselectAsset} from "../Search/Layout/Layout";
import {getAssetListFromEvent} from "../Search/AssetResults";
import {AssetSelectionContext} from "../AssetSelectionContext";
import {DisplayContext, TDisplayContext} from "../DisplayContext";
import {voidFunc} from "../../../lib/utils";
import {Box, Checkbox, FormControlLabel} from "@mui/material";
import {useTranslation} from 'react-i18next';

type Props = {
    assets: Asset[];
    onSelectionChange: (selection: string[]) => void;
} & {
    style?: CSSProperties;
};

function SelectionProxy({
    pages,
}: {
    pages: Asset[][];
}) {
    const {t} = useTranslation();
    const assetSelection = useContext(AssetSelectionContext);

    const onSelect = useCallback<OnSelectAsset>((id, e): void => {
        e?.preventDefault();
        assetSelection.selectAssets((prev) => {
            return getAssetListFromEvent(prev, id, pages, e)
        });
        // eslint-disable-next-line
    }, [pages]);

    const onUnselect = useCallback<OnUnselectAsset>((id, e): void => {
        e?.preventDefault();
        assetSelection.selectAssets(p => p.filter(i => i !== id));
        // eslint-disable-next-line
    }, []);

    return <div>
        <FormControlLabel
            control={<Checkbox
                checked={assetSelection.selectedAssets.length === pages[0].length}
                onChange={(e, checked) => {
                    assetSelection.selectAssets(checked ? pages[0].map(a => a.id) : []);
                }}
            />}
            label={`${t('form.copy_assets.asset_not_linkable.toggle_select_all', 'Select/Unselect all')} (${assetSelection.selectedAssets.length}/${pages[0].length})`}
            labelPlacement="end"
        />
        <Pager
            pages={pages}
            layout={LayoutEnum.List}
            selectedAssets={assetSelection.selectedAssets}
            onSelect={onSelect}
            onUnselect={onUnselect}
        />
    </div>
}

export default function AssetSelection({
    assets,
    onSelectionChange,
    style,
}: Props) {
    const pages = useMemo(() => [assets], [assets]);

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
        thumbSize: 100,
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
                pages,
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
                        pages={pages}
                    />
                </DisplayContext.Provider>
            </AssetSelectionProvider>
        </ResultContext.Provider>
    </Box>
}
