import {Asset} from "../../../../types";
import {MouseEvent} from "react";

export type LayoutProps = {
    assets: Asset[];
    onSelect: (id: string, e: MouseEvent) => void;
    selectedAssets: string[];
}
