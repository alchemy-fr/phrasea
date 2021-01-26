import React, {PureComponent, MouseEvent} from "react";
import AssetItem from "./AssetItem";
import {getAssets} from "../../api/asset";
import {Asset} from "../../types";
import {SelectionContext, TSelectionContext} from "./SelectionContext";

type Props = {
    query: string;
};

type State = {
    data: Asset[];
};

function getAssetListFromEvent(currentSelection: string[], id: string, e: MouseEvent): string[] {
    if (e.ctrlKey) {
        return currentSelection.includes(id) ? currentSelection.filter(a => a !== id) : currentSelection.concat([id]);
    }

    return [id];
}

function extractCollectionIdFromPath(path: string): string {
    const p = path.split('/');
    return p[p.length - 1];
}

export default class AssetGrid extends PureComponent<Props, State> {
    static contextType = SelectionContext;
    context: React.ContextType<typeof SelectionContext>;

    state: State = {
        data: [],
    };

    lastContext: TSelectionContext;

    componentDidMount() {
        this.load();
    }

    componentDidUpdate(prevProps: Readonly<Props>, prevState: Readonly<State>) {
        if (
            this.lastContext !== this.context
        ) {
            this.lastContext = this.context;

            this.load();
        }
    }

    async load() {
        const parents = this.context.selectedCollection ? [extractCollectionIdFromPath(this.context.selectedCollection)] : undefined;

        const data = await getAssets({
            query: this.props.query,
            parents,
        });

        this.setState({data});
    }

    onSelect = (id: string, e: MouseEvent): void => {
        let ids = getAssetListFromEvent(this.context.selectedAssets, id, e);

        this.context.selectAssets(ids);
    }

    render() {
        return <div className="asset-grid">
            {this.renderResult()}
        </div>
    }

    renderResult() {
        const {data} = this.state;

        return data.map(a => <AssetItem
            {...a}
            selected={this.context.selectedAssets.includes(a.id)}
            onClick={this.onSelect}
            key={a.id}
        />);
    }
}
