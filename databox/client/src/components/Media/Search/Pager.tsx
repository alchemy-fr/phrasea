import GridLayout from "./Layout/GridLayout";
import ListLayout from "./Layout/ListLayout";
import React from "react";
import {Asset} from "../../../types";
import {Box} from "@mui/material";
import {OnOpen, OnPreviewToggle, OnSelectAsset, OnUnselectAsset, TOnContextMenuOpen} from "./Layout/Layout";
import SectionDivider from "./Layout/SectionDivider";

export enum LayoutEnum {
    Grid,
    List,
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
                    rootStyle={theme => ({
                        top: 49,
                    })}
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
                    page: i + 1,
                })}
            </React.Fragment>
        })}
    </Box>
})
