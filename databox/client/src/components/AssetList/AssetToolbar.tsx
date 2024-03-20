import {LinearProgress, ListSubheader} from "@mui/material";
import {zIndex} from "../../themes/zIndex.ts";
import SearchBar from "../Media/Search/SearchBar.tsx";
import SelectionActions from "../Media/Search/SelectionActions.tsx";
import {searchMenuId} from "../Media/Search/AssetResults.tsx";
import {Layout} from "./Layouts";
import {StateSetter} from "../../types.ts";

type Props = {
    loading?: boolean;
    layout: Layout;
    setLayout: StateSetter<Layout>;
};

export default function AssetToolbar({
    loading,
    layout,
    setLayout,
}: Props) {
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
        <div>
            <ListSubheader
                id={searchMenuId}
                component="div"
                disableGutters={true}
                sx={() => ({
                    zIndex: zIndex.toolbar,
                })}
            >
                <SearchBar/>
                <SelectionActions
                    layout={layout}
                    setLayout={setLayout}
                />
            </ListSubheader>
        </div>
    </>
}
