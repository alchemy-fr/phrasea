import {MouseEvent, PureComponent} from "react";
import {Asset} from "../../types";

type Props = {
    selected?: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
}

export default class AssetItem extends PureComponent<Props & Asset> {
    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props.id, e);
    }

    render() {
        return <div
            onClick={this.onClick}

            className={`asset-item ${this.props.selected ? 'selected' : ''}`}>
            <div className="a-thumb">
                <img
                    src="https://user-images.githubusercontent.com/194400/49531010-48dad180-f8b1-11e8-8d89-1e61320e1d82.png"
                    alt="Placeholder"/>
            </div>
            <div className="a-footer">
                <div className="a-title">
                    {this.props.title}
                </div>
                <p className="a-desc">
                    {this.props.description}
                    {this.props.public ? 'Public' : 'Private'}
                </p>
            </div>
        </div>
    }
}
