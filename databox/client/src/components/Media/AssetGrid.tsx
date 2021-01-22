import {PureComponent} from "react";
import AssetItem from "./AssetItem";
import {getAssets} from "../../api/asset";
import {Asset} from "../../types";

type Props = {
    query: string;
};
type State = {
    data: Asset[];
};

export default class AssetGrid extends PureComponent<Props, State> {
    state: State = {
        data: [],
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const data = await getAssets({
            query: this.props.query,
            workspaces: null,
        });

        this.setState({data});
    }

    render() {
        return <div className="container">
            <div className="row">
                {this.renderResult()}
            </div>
        </div>
    }

    renderResult() {
        const {data} = this.state;

        return data.map(a => <AssetItem
            {...a}
            key={a.id}
        />);
    }
}
