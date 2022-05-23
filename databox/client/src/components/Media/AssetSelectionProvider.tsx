import React, {PropsWithChildren, useContext, useEffect, useState} from "react";
import {AssetSelectionContext} from "./AssetSelectionContext";
import {ResultContext} from "./Search/ResultContext";

export default function AssetSelectionProvider({children}: PropsWithChildren<{}>) {
    const resultContext = useContext(ResultContext);
    const [selectedAssets, setSelectedAssets] = useState<string[]>([]);

    useEffect(() => {
        setSelectedAssets([]);
    }, [resultContext.pages]);

    return <AssetSelectionContext.Provider value={{
        selectedAssets,
        selectAssets: setSelectedAssets,
    }}>
        {children}
    </AssetSelectionContext.Provider>
}
