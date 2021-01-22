import {PureComponent} from "react";
import AssetItem from "./AssetItem";
import {getAssets} from "../../api/asset";

type Props = {
    query: string;
};
type State = {
    data: any[];
};

export default class AssetsResult extends PureComponent<Props, State> {
    state = {
        data: [],
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const data = await getAssets({
            query: ``,
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
        />);
    }
}
