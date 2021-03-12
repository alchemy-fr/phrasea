import React, {PureComponent, MouseEvent} from "react";
import AssetItem from "./AssetItem";
import {getAssets} from "../../api/asset";
import {Asset} from "../../types";
import {SelectionContext, TSelectionContext} from "./SelectionContext";
import {Button, GridList, GridListTile, LinearProgress, ListSubheader} from "@material-ui/core";

type Props = {
    query: string;
};

type State = {
    data: Asset[];
    total?: number;
    loading: boolean;
    next?: string | null;
};

const classes = {
    root: {
        display: 'flex',
        flexWrap: 'wrap' as 'wrap',
        justifyContent: 'space-around',
        overflow: 'hidden',
        width: '100%',
    },
    gridList: {
        width: '100%',
    },
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
        this.load();
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
        });
    }

    loadMore = (): void => {
        this.setState({loading: true}, () => {
            this.load('/..' + this.state.next!);
        });
    }

    onSelect = (id: string, e: MouseEvent): void => {
        let ids = getAssetListFromEvent(this.context.selectedAssets, id, e);

        this.context.selectAssets(ids);
    }

    render() {
        const {total, next, loading} = this.state;

        return <div style={{position: 'relative', width: '100%'}}>
            {loading && <div style={{
                position: 'absolute',
                left: '0',
                right: '0',
                top: '0',
            }}>
                <LinearProgress/>
            </div>}
            <div style={classes.root}>
                <GridList cellHeight={180} style={classes.gridList}>
                    <GridListTile key="Subheader" cols={2} style={{height: 'auto'}}>
                        <ListSubheader component="div">
                            {!loading && total !== undefined ? `${total} result${total > 1 ? 's' : ''}` : 'Loading...'}
                        </ListSubheader>
                    </GridListTile>
                    {this.renderResult()}
                </GridList>
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
