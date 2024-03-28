import React from "react";
import {AssetOrAssetContainer} from "../../../../types.ts";
import {LayoutPageProps, OnPreviewToggle} from "../../types.ts";


type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
    displayAttributes: boolean;
} & LayoutPageProps<Item>;

export default class VirtualizedList extends React.PureComponent<Props> {
    constructor(props, context) {
        super(props, context);

        this.state = {
            list: [...Array(100).keys()],
        }
        this._cache = new ReactVirtualized.CellMeasurerCache({
            fixedWidth: true,
            keyMapper: index => this.state.list[index]
        });

        this._rowRenderer = this._rowRenderer.bind(this);
    }

    componentDidUpdate(prevProps, prevState) {
        const newRows= this.state.list.filter(value => prevState.list.indexOf(value) < 0);
        const newRowsIndex = newRows.map(value => this.state.list.indexOf(value));

        newRowsIndex.forEach(index => this._cache.clear(index));
        newRowsIndex.length && this._list.recomputeRowHeights(Math.min([...newRowsIndex]))
    }

    removeImg(index) {
        const list = [...this.state.list];
        list.splice(index, 1);
        this.setState({
            list,
        });

        this._list.recomputeRowHeights(index);
    }

    addRowBelow(index) {
        const list = [...this.state.list];
        list.splice(index + 1, 0,  Math.max(...list) + 1);

        this.setState({
            list,
        })
    }


    removeAndAddRows(index) {
        const list = [...this.state.list];
        const newValue = Math.max(...list) + 1;

        list.splice(index, 1);
        list.splice(index, 1);
        list.splice(index, 0,  newValue);

        this.setState({
            list,
        })
    }

    _rowRenderer({index, key, parent, style}) {
        return (
            <ReactVirtualized.CellMeasurer
                cache={this._cache}
                columnIndex={0}
                key={key}
                rowIndex={index}
                parent={parent}>
                {({measure}) => (
                    <div style={style}>
                        <div className="item" style={{height:20 + this.state.list[index]}}>
                            <span>{this.state.list[index] + 1}</span>
                            <button onClick={this.removeImg.bind(this, index)}>Delete</button>
                            <button onClick={this.addRowBelow.bind(this, index)}>Add row below</button>
                            <button onClick={this.removeAndAddRows.bind(this, index)}>Remove 2 rows and add 1 row</button>
                        </div>
                    </div>
                )}
            </ReactVirtualized.CellMeasurer>
        );
    }

    render() {
        return (
            <ReactVirtualized.List
                ref={element => {
                    this._list = element;
                }}
                deferredMeasurementCache={this._cache}
                overscanRowCount={0}
                rowCount={this.state.list.length}
                rowHeight={this._cache.rowHeight}
                rowRenderer={this._rowRenderer}
                width={500}
                height={500}
            />
        );
    }
}
