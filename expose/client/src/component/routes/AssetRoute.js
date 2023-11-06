import React, {PureComponent} from 'react';
import Publication from "../Publication";

class AssetRoute extends PureComponent {
    render() {
        return <Publication
            id={this.props.match.params.publication}
            assetId={this.props.match.params.asset}
            username={this.props.username}
        />
    }
}

export default AssetRoute;
