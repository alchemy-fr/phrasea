import React, {PureComponent, MouseEvent} from "react";
import AssetItem from "./AssetItem";
import {getAssets} from "../../api/asset";
import {Asset} from "../../types";
import {SelectionContext, TSelectionContext} from "./SelectionContext";
import Button from "../ui/Button";

type Props = {
    query: string;
};

type State = {
    data: Asset[];
    total?: number;
    loading: boolean;
    next?: string | null;
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
        loading: true,
    };

    lastContext: TSelectionContext;

    lastSearch: string = '';

    componentDidMount() {
        this.lastContext = this.context;
        this.search();
    }

    componentDidUpdate(prevProps: Readonly<Props>, prevState: Readonly<State>) {
        if (
            this.lastContext !== this.context
        ) {
            this.lastContext = this.context;

            this.search();
        }
    }

    search() {
        this.setState({
            loading: true,
            total: undefined,
        }, () => {
            this.load();
        });
    }

    async load(url?: string) {
        const parents = this.context.selectedCollection ? [extractCollectionIdFromPath(this.context.selectedCollection)] : undefined;

        const options = {
            query: this.props.query,
            parents,
            workspaces: this.context.selectedWorkspace ? [this.context.selectedWorkspace] : undefined,
            url,
        };

        const searchHash = JSON.stringify(options);

        if (searchHash === this.lastSearch) {
            return;
        }
        this.lastSearch = searchHash;
        const result = await getAssets(options);

        // Append?
        if (url) {
            this.setState(prevState => ({
                loading: false,
                data: prevState.data.concat(result.result),
                total: result.total,
                next: result.next,
            }));
        } else {
            this.setState({
                loading: false,
                data: result.result,
                total: result.total,
                next: result.next,
            });
        }
    }

    loadMore = (): void => {
        this.setState({loading: true}, () => {
            this.load('/..'+this.state.next!);
        });
    }

    onSelect = (id: string, e: MouseEvent): void => {
        let ids = getAssetListFromEvent(this.context.selectedAssets, id, e);

        this.context.selectAssets(ids);
    }

    render() {
        const {total, next, loading} = this.state;

        return <div>
            <div>
                {total !== undefined ? `${total} result${total > 1 ? 's' : ''}` : 'Loading...'}
            </div>
            <div className="asset-grid">
                {this.renderResult()}
            </div>
            <div>
                {next ? <Button
                    onClick={this.loadMore}
                    disabled={loading}
                >
                    {loading ? 'Loading...' : 'Load more'}
                </Button> : ''}
            </div>
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
