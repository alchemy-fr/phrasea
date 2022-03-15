import React, {PropsWithChildren, useState} from "react";
import {SearchFiltersContext} from "./SearchFiltersContext";

type State = {
    selectedWorkspace?: string | undefined;
    selectedCollection?: string | undefined;
    reloadInc: number;
};

export default function SearchFiltersProvider({children}: PropsWithChildren<{}>) {
    const [state, setState] = useState<State>({
        reloadInc: 0,
    });

    const selectWorkspace = (id: string | undefined, forceReload?: boolean) => {
        setState(prev => ({
            ...prev,
            selectedWorkspace: id,
            selectedCollection: undefined,
            reloadInc: forceReload ? prev.reloadInc + 1 : prev.reloadInc,
        }));
    };
    const selectCollection = (absolutePath: string | undefined, forceReload?: boolean) => {
        setState(prev => ({
            ...prev,
            selectedWorkspace: undefined,
            selectedCollection: absolutePath,
            reloadInc: forceReload ? prev.reloadInc + 1 : prev.reloadInc,
        }));
    };

    return <SearchFiltersContext.Provider value={{
        selectWorkspace,
        selectCollection,
        selectedWorkspace: state.selectedWorkspace,
        selectedCollection: state.selectedCollection,
        reloadInc: state.reloadInc,
    }}>
        {children}
    </SearchFiltersContext.Provider>
}
