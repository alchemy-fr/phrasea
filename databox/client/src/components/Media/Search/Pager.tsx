import GridLayout from "./Layout/GridLayout";
import ListLayout from "./Layout/ListLayout";
import React, {useEffect} from "react";
import {Asset} from "../../../types";
import {Box} from "@mui/material";
import {OnOpen, OnPreviewToggle, OnSelectAsset, OnUnselectAsset, TOnContextMenuOpen} from "./Layout/Layout";
import SectionDivider from "./Layout/SectionDivider";
import useWindowSize from "../../../hooks/useWindowSize";
import {searchMenuId} from "./AssetResults";

export enum LayoutEnum {
    Grid = 'grid',
    List = 'list',
}

type Props = {
    pages: Asset[][];
    layout: LayoutEnum;
    selectedAssets: string[];
    onOpen?: OnOpen;
    onSelect: OnSelectAsset;
    onUnselect: OnUnselectAsset;
    onPreviewToggle?: OnPreviewToggle;
    onContextMenuOpen?: TOnContextMenuOpen;
};

export default React.memo<Props>(function Pager({
    pages,
    layout,
    selectedAssets,
    onSelect,
    onUnselect,
    onOpen,
    onContextMenuOpen,
    onPreviewToggle,
}: Props) {
    const [searchMenuHeight, setSearchMenuHeight] = React.useState(document.getElementById(searchMenuId)?.offsetHeight ?? 0);

    useEffect(() => {
        const resizeObserver = new ResizeObserver((entries) => {
            setSearchMenuHeight(entries[0].target.clientHeight);
        });

        resizeObserver.observe(document.getElementById(searchMenuId)!);

        return () => {
            resizeObserver.disconnect();
        }
    }, []);

    return <Box
        sx={{
            bgcolor: 'common.white',
        }}
    >
        {pages.map((assets, i) => {
            return <React.Fragment
                key={i}
            >
                {i > 0 && <SectionDivider
                    top={searchMenuHeight}
                    textStyle={() => ({
                        fontWeight: 700,
                        fontSize: 15,
                    })}

                ># {i + 1}</SectionDivider>}
                {React.createElement(layout === LayoutEnum.Grid ? GridLayout : ListLayout, {
                    assets,
                    onSelect,
                    onUnselect,
                    onOpen,
                    selectedAssets,
                    onContextMenuOpen,
                    onPreviewToggle,
                    searchMenuHeight,
                    page: i + 1,
                })}
            </React.Fragment>
        })}
    </Box>
})
