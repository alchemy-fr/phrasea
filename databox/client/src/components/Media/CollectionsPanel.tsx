import {PureComponent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import CollectionItem from "./CollectionItem";

type State = {
    data: Collection[];
};

export default class CollectionsPanel extends PureComponent<{}, State> {
    state: State = {
        data: [],
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const data = await getCollections({
            query: ``,
        });

        this.setState({data});
    }

    render() {
        return <div className="collections">
            {this.renderResult()}
        </div>
    }

    renderResult() {
        const {data} = this.state;

        return data.map(c => <CollectionItem
            {...c}
            key={c.id}
        />);
    }
}
