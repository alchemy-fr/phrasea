import {LinearProgress, ListSubheader} from "@mui/material";
import {zIndex} from "../../themes/zIndex.ts";
import SearchBar from "../Media/Search/SearchBar.tsx";
import SelectionActions, {SelectionActionsProps} from "./Toolbar/SelectionActions.tsx";
import {AssetOrAssetContainer} from "../../types.ts";
import assetClasses from "./classes.ts";

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
    actions,
    searchBar = true,
}: Props<Item>) {
    return <>
        <ListSubheader
            className={assetClasses.assetToolbar}
            component="div"
            disableGutters={true}
            sx={() => ({
                zIndex: zIndex.toolbar,
            })}
        >
            {searchBar ? <SearchBar/> : ''}
            <SelectionActions
                actions={actions}
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
        {loading && (
            <div style={{
                position: 'absolute',
                left: '0',
                right: '0',
                zIndex: 100,
            }}>
                <LinearProgress/>
            </div>
        )}
    </>
}
