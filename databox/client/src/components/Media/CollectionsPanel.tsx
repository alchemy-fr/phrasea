import React, {MouseEvent, PureComponent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import CollectionMenuItem, {CollectionMenuItemProps} from "./CollectionMenuItem";
import {SelectionContext} from "./SelectionContext";

type State = {
    data: Collection[];
};

export default class CollectionsPanel extends PureComponent<{}, State> {
    static contextType = SelectionContext;
    context: React.ContextType<typeof SelectionContext>;

    state: State = {
        data: [],
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const data = await getCollections({});

        this.setState({data});
    }

    onSelect = (collection: CollectionMenuItemProps, e: MouseEvent): void => {
        this.context.selectCollection(collection.absolutePath);
    }

    render() {
        return <div className="collections">
            {this.renderResult()}
        </div>
    }

    renderResult() {
        const {data} = this.state;

        return data.map(c => <CollectionMenuItem
            {...c}
            key={c.id}
            absolutePath={c.id}
            selectedPath={this.context.selectedCollection}
            onClick={this.onSelect}
            level={0}
        />);
    }
}
