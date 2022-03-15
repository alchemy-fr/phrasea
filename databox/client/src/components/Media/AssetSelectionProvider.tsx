import React, {PropsWithChildren, useState} from "react";
import {AssetSelectionContext} from "./AssetSelectionContext";

export default function AssetSelectionProvider({children}: PropsWithChildren<{}>) {
    const [selectedAssets, setSelectedAssets] = useState<string[]>([]);

    return <AssetSelectionContext.Provider value={{
        selectedAssets,
        selectAssets: setSelectedAssets,
    }}>
        {children}
    </AssetSelectionContext.Provider>
}
