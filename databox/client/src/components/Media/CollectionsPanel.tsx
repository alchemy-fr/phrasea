import {PureComponent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import CollectionMenuItem from "./CollectionMenuItem";

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
        const data = await getCollections({});

        this.setState({data});
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
            level={0}
        />);
    }
}
