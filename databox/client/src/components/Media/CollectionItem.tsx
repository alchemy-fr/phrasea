import {PureComponent} from "react";
import {Collection} from "../../types";

export default class CollectionItem extends PureComponent<Collection, {}> {
    render() {
        return <div className="collection-item">
            {this.props.title}
        </div>
    }
}
