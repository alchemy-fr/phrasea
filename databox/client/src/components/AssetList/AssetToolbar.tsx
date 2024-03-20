import {LinearProgress, ListSubheader} from "@mui/material";
import {zIndex} from "../../themes/zIndex.ts";
import SearchBar from "../Media/Search/SearchBar.tsx";
import SelectionActions, {SelectionActionsProps} from "./Toolbar/SelectionActions.tsx";
import {searchMenuId} from "../Media/Search/AssetResults.tsx";
import {AssetOrAssetContainer} from "../../types.ts";

type Props<Item extends AssetOrAssetContainer> = {
    searchBar?: boolean;
} & SelectionActionsProps<Item>;

export default function AssetToolbar<Item extends AssetOrAssetContainer>({
    loading,
    total,
    layout,
    setLayout,
    reload,
    pages,
    onOpenDebug,
    selectionContext,
    searchBar = true,
}: Props<Item>) {
    return <>
        {loading && (
            <div style={{
                position: 'absolute',
                left: '0',
                right: '0',
                top: '0',
            }}>
                <LinearProgress/>
            </div>
        )}
            <ListSubheader
                id={searchMenuId}
                component="div"
                disableGutters={true}
                sx={() => ({
                    zIndex: zIndex.toolbar,
                })}
            >
                {searchBar ? <SearchBar/> : ''}
                <SelectionActions
                    reload={reload}
                    pages={pages}
                    layout={layout}
                    setLayout={setLayout}
                    loading={loading}
                    total={total}
                    onOpenDebug={onOpenDebug}
                    selectionContext={selectionContext}
                />
            </ListSubheader>
    </>
}
