import React, {PureComponent} from "react";
import {SelectionContext} from "./SelectionContext";
import {CollectionMenuItemProps} from "./CollectionMenuItem";

type State = {
    selectedWorkspace?: string;
    selectedCollection?: string;
    selectedAssets: string[];
    reloadInc: number;
};

type Props = {};

export default class MediaSelection extends PureComponent<Props, State>
{
    state = {
        selectedAssets: [],
        reloadInc: 0,
    };

    selectCollection = (absolutePath: string, forceReload?: boolean): void => {
        this.setState((prevState) => {
            const newState: Pick<State, "selectedCollection" | "reloadInc"> = {
                selectedCollection: absolutePath,
                reloadInc: prevState.reloadInc,
            };

            if (forceReload) {
                newState.reloadInc++;
            }

            return newState;
        });
    }

    selectWorkspace = (id: string, forceReload?: boolean): void => {
        this.setState((prevState) => {
            const newState: Pick<State, "selectedCollection" | "selectedWorkspace" | "reloadInc"> = {
                selectedWorkspace: id,
                selectedCollection: undefined,
                reloadInc: prevState.reloadInc,
            };

            if (forceReload) {
                newState.reloadInc++;
            }

            return newState;
        });
    }

    selectAssets = (ids: string[]): void => {
        this.setState({selectedAssets: ids});
    }

    resetAssetSelection = (): void => {
        this.setState({selectedAssets: []});
    };

    render() {
        return <SelectionContext.Provider value={{
            ...this.state,
            selectWorkspace: this.selectWorkspace,
            selectCollection: this.selectCollection,
            selectAssets: this.selectAssets,
            resetAssetSelection: this.resetAssetSelection,
        }}>
            {this.props.children}
        </SelectionContext.Provider>
    }
}
