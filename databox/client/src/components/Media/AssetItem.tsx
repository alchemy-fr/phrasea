import {PureComponent} from "react";
import {Asset} from "../../types";

const imagePlaceholder = <svg className="bd-placeholder-img card-img-top" width="100%" height="180"
                              xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Placeholder: Image cap"
                              preserveAspectRatio="xMidYMid slice" focusable="false"><title>Placeholder</title>
    <rect width="100%" height="100%" fill="#868e96" />
    <text x="50%" y="50%" fill="#dee2e6" dy=".3em">Image cap</text>
</svg>;

export default class AssetItem extends PureComponent<Asset, {}> {
    render() {
        return <div className="asset-item">
            <div className="card">
                {imagePlaceholder}
                <div className="card-body">
                    <h5 className="card-title">{this.props.title}</h5>
                    <p className="card-text">{this.props.description}</p>
                    <p className="card-text">{this.props.public ? 'Public' : 'Private'}</p>
                </div>
            </div>
        </div>
    }
}
