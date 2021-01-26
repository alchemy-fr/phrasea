import React, {PureComponent} from "react";
import {SelectionContext} from "./SelectionContext";
import {CollectionMenuItemProps} from "./CollectionMenuItem";

type State = {
    selectedCollection?: string;
    selectedAssets: string[];
};

export default class MediaSelection extends PureComponent<{}, State>
{
    state = {
        selectedAssets: [],
    };

    selectCollection = (absolutePath: string): void => {
        this.setState({selectedCollection: absolutePath});
    }

    selectAssets = (ids: string[]): void => {
        this.setState({selectedAssets: ids});
    }

    render() {
        return <SelectionContext.Provider value={{
            ...this.state,
            selectCollection: this.selectCollection,
            selectAssets: this.selectAssets,
        }}>
            {this.props.children}
        </SelectionContext.Provider>
    }
}
