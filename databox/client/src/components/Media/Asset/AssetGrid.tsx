import React, {CSSProperties, MouseEvent, PureComponent} from "react";
import AssetItem from "./AssetItem";
import {getAssets} from "../../../api/asset";
import {Asset} from "../../../types";
import {SelectionContext, TSelectionContext} from "../SelectionContext";
import {Button, ImageList, LinearProgress, ListSubheader} from "@material-ui/core";

type Props = {
    query: string;
};

type State = {
    data: Asset[][];
    total?: number;
    loading: boolean;
    next?: string | null;
};

const classes = {
    root: {
    },
    gridList: {
        width: '100%',
    },
};

const gridStyle: CSSProperties = {
    width: '100%',
    height: '100%',
    overflow: 'auto',
};

const linearProgressStyle: CSSProperties = {
    position: 'absolute',
    left: '0',
    right: '0',
    top: '0',
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
        if (prevProps.query !== this.props.query) {
            this.context.resetAssetSelection();
        }

        if (
            (this.lastContext !== this.context
                && (
                    this.lastContext.selectedCollection !== this.context.selectedCollection
                    || this.lastContext.selectedWorkspace !== this.context.selectedWorkspace
                    || this.lastContext.reloadInc < this.context.reloadInc
                ))
            || prevProps.query !== this.props.query
        ) {
            this.search(undefined, this.lastContext.reloadInc < this.context.reloadInc);
            this.lastContext = this.context;
        }
    }

    async search(url?: string, force?: boolean) {
        const parents = this.context.selectedCollection ? [extractCollectionIdFromPath(this.context.selectedCollection)] : undefined;

        const options = {
            query: this.props.query,
            parents,
            workspaces: this.context.selectedWorkspace ? [this.context.selectedWorkspace] : undefined,
            url,
        };

        const searchHash = JSON.stringify(options);
        if (!force && searchHash === this.lastSearch) {
            return;
        }

        this.context.resetAssetSelection();

        this.setState({
            loading: true,
            total: undefined,
        }, async () => {
            this.lastSearch = searchHash;
            const result = await getAssets(options);

            // Append?
            if (url) {
                this.setState(prevState => ({
                    loading: false,
                    data: prevState.data.concat([result.result]),
                    total: result.total,
                    next: result.next,
                }));
            } else {
                this.setState({
                    loading: false,
                    data: [result.result],
                    total: result.total,
                    next: result.next,
                });
            }
        });
    }

    loadMore = (): void => {
        this.setState({loading: true}, () => {
            this.search('/..' + this.state.next!);
        });
    }

    onSelect = (id: string, e: MouseEvent): void => {
        let ids = getAssetListFromEvent(this.context.selectedAssets, id, e);

        this.context.selectAssets(ids);
    }

    render() {
        const {total, next, loading} = this.state;

        return <div style={{
            position: 'relative',
            height: '100%',
        }}>
            <div style={gridStyle}>
                {loading && <div style={linearProgressStyle}>
                    <LinearProgress/>
                </div>}
                <div style={classes.root}>
                    <ListSubheader component="div" className={'result-info'}>
                        {!loading && total !== undefined ? <>
                            <b>
                                {new Intl.NumberFormat('fr-FR', {}).format(total)}
                            </b>
                            {` result${total > 1 ? 's' : ''}`}
                        </> : 'Loading...'}
                    </ListSubheader>
                    <div className={'grid-results'}>
                        {this.renderResult()}
                    </div>
                </div>
                {next ? <div className={'text-center mb-3'}>
                    <Button
                        disabled={loading}
                        onClick={this.loadMore}
                        variant="contained"
                        color="secondary"
                    >
                        {loading ? 'Loading...' : 'Load more'}
                    </Button>
                </div> : ''}
            </div>
        </div>
    }

    renderResult() {
        const {data} = this.state;

        return data.map((rs, i) => <div
            key={i}
            className={'result-page'}>
            <div className="page-num"># {i + 1}</div>
            <ImageList rowHeight={180} style={classes.gridList}>
                {rs.map(a => <AssetItem
                    {...a}
                    selected={this.context.selectedAssets.includes(a.id)}
                    onClick={this.onSelect}
                    key={a.id}
                />)}
            </ImageList>
        </div>)
    }
}
